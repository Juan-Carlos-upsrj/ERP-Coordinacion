<?php
/**
 * app/services/ScheduleGeneratorService.php
 * Algoritmo de asignación automática de horarios.
 */

class ScheduleGeneratorService {
    private $pdo;
    private $carrera_id;
    private $config;
    
    // Almacenamiento temporal de ocupación para el algoritmo
    private $docente_busyness = []; // docente_id -> [dia][hora] = true
    private $grupo_busyness   = []; // grupo_id   -> [dia][hora] = true
    private $aula_busyness    = []; // aula_id    -> [dia][hora] = true
    private $docente_hours    = []; // docente_id -> [diaria=>X, semanal=>Y]
    private $current_period   = null;
    private $hasPeriodoColumn = false;
    private $filters = [];
    
    private $unassigned = [];
    private $assigned_clases = [];
    private $preferences      = []; // materia_id -> [docente_id1, ...]
    
    public function __construct(PDO $pdo, int $carrera_id) {
        $this->pdo = $pdo;
        $this->carrera_id = $carrera_id;
        $this->config = HorariosModel::getConfig($pdo, $carrera_id);
    }

    /**
     * Genera el horario automáticamente.
     * @param bool $dryRun Si es true, no guarda en DB, solo retorna el plan.
     * @param array $filters Opcional: ['docente_ids' => [...], 'grupo_ids' => [...]]
     */
    public function generate(bool $dryRun = true, array $filters = []) {
        $this->unassigned = [];
        $this->assigned_clases = [];
        
        // Determinar periodo de trabajo
        $this->current_period = $filters['periodo'] ?? ($this->config['anio_activo'] . '-' . $this->config['cuatrimestre_activo']);
        $this->filters = $filters;
        $isPlanning = ($this->current_period !== ($this->config['anio_activo'] . '-' . $this->config['cuatrimestre_activo']));

        // Detectar si existe la columna periodo de forma robusta
        $checkSql = "SELECT 1 FROM information_schema.columns WHERE table_name = 'hor_clases' AND column_name = 'periodo'";
        try {
            $stmt = $this->pdo->query($checkSql);
            $this->hasPeriodoColumn = (bool)$stmt->fetchColumn();
        } catch (\Exception $e) {
            $this->hasPeriodoColumn = false;
        }

        // 1. Cargar datos
        $docentes = HorariosModel::getDocentes($this->pdo, $this->carrera_id);
        $materias = HorariosModel::getMaterias($this->pdo, $this->carrera_id);
        
        // Excluir materias marcadas como externas
        $materias = array_filter($materias, function($m) {
            return empty($m['es_externa']);
        });

        $grupos   = HorariosModel::getGrupos($this->pdo, $this->carrera_id);
        $aulas    = HorariosModel::getAulas($this->pdo, $this->carrera_id);
        $this->preferences = HorariosModel::getDocenteMateriasByCarrera($this->pdo, $this->carrera_id);

        // Aplicar filtros si existen
        if (!empty($filters['docente_ids'])) {
            $docentes = array_filter($docentes, function($d) use ($filters) {
                return in_array($d['id'], $filters['docente_ids']);
            });
        }
        if (!empty($filters['grupo_ids'])) {
            $grupos = array_filter($grupos, function($g) use ($filters) {
                return in_array($g['id'], $filters['grupo_ids']);
            });
        }
        
        // 2. Inicializar estructuras de seguimiento
        $this->initOcupacion($docentes, $grupos, $aulas);
        
        // 3. Priorizar materias (por cuatrimestre y prioridad)
        usort($materias, function($a, $b) {
            $prioMap = ['Alta' => 1, 'Media' => 2, 'Baja' => 3];
            $pa = $prioMap[$a['prioridad'] ?? 'Media'] ?? 2;
            $pb = $prioMap[$b['prioridad'] ?? 'Media'] ?? 2;
            if ($pa !== $pb) return $pa - $pb;
            return ($a['cuatrimestre'] ?? 0) - ($b['cuatrimestre'] ?? 0);
        });

        // 4. Intentar asignar cada materia a cada grupo correspondiente
        foreach ($grupos as $grupo) {
            $target_cuatri = (int)$grupo['cuatrimestre'] + ($isPlanning ? 1 : 0);
            $max_cuatri = $this->config['max_cuatrimestres'] ?? 10;
            
            // Si el grupo se pasa del máximo (egresa) o llega al máximo (y el máximo es típicamente puras estadias), 
            // no tratamos de asignarle clases teóricas si no las tiene (filtradas abajo).
            if ($target_cuatri > $max_cuatri) continue;

            $grupoPlan = !empty($grupo['plan']) ? $grupo['plan'] : 'Plan Regular';
            
            $materias_grupo = array_filter($materias, function($m) use ($target_cuatri, $grupoPlan) {
                if ((int)$m['cuatrimestre'] !== $target_cuatri) return false;
                // Excluir automáticamente materias prácticas completas como Estadías que no ocurren físicamente en horarios normales
                if (stripos($m['nombre'], 'estadía') !== false || stripos($m['nombre'], 'estadia') !== false) return false;
                
                $materiaPlan = !empty($m['plan']) ? $m['plan'] : 'Plan Regular';
                return $materiaPlan === $grupoPlan;
            });

            // Si el grupo no tiene materias válidas (ej. solo Estadías fue filtrada), lo omitimos de la planificación.
            if (empty($materias_grupo)) continue;

            foreach ($materias_grupo as $materia) {

                // Inyectamos el target_cuatri temporal en el array del grupo para que isAvailable y otros calculen los turnos con base en su cuatrimestre futuro.
                $grupo_promovido = $grupo;
                $grupo_promovido['cuatrimestre'] = $target_cuatri;

                $success = $this->assignMateriaToGrupo($materia, $grupo_promovido, $docentes, $aulas);
                if (!$success) {
                    $this->unassigned[] = [
                        'materia' => $materia['nombre'],
                        'grupo'   => $grupo['nombre'],
                        'cuatrimestre' => $target_cuatri
                    ];
                }
            }
        }

        // 5. Persistir si no es Dry Run
        if (!$dryRun) {
            $this->saveToDatabase();
        }

        return [
            'success' => true,
            'assigned' => count($this->assigned_clases),
            'unassigned' => $this->unassigned,
            'clases' => $this->assigned_clases
        ];
    }

    private function initOcupacion($docentes, $grupos, $aulas) {
        $grupo_ids = array_map(function($g){ return $g['id']; }, $grupos);
        $placeholders = count($grupo_ids) ? implode(',', array_fill(0, count($grupo_ids), '?')) : 'NULL';

        foreach ($docentes as $d) {
            $this->docente_busyness[$d['id']] = [];
            $this->docente_hours[$d['id']] = ['diaria' => [], 'semanal' => 0];
            
            // Cargar ocupación real (excluyendo clases de los grupos que vamos a regenerar)
            $sql = "SELECT dia, hora_inicio, duracion FROM hor_clases WHERE docente_id = ?";
            $params = [$d['id']];

            // Excluir grupos que se están regenerando (para liberar sus espacios)
            if (count($grupo_ids) > 0) {
                $sql .= " AND grupo_id NOT IN ($placeholders)";
                $params = array_merge($params, $grupo_ids);
            }

            if ($this->hasPeriodoColumn) {
                $sql .= " AND periodo = ?";
                $params[] = $this->current_period;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            foreach ($stmt->fetchAll() as $c) {
                for ($h = $c['hora_inicio']; $h < $c['hora_inicio'] + $c['duracion']; $h++) {
                    $this->docente_busyness[$d['id']][$c['dia']][$h] = true;
                }
                $this->docente_hours[$d['id']]['semanal'] += (int)$c['duracion'];
                $dia = (int)$c['dia'];
                $this->docente_hours[$d['id']]['diaria'][$dia] = ($this->docente_hours[$d['id']]['diaria'][$dia] ?? 0) + (int)$c['duracion'];
            }
        }
        foreach ($grupos as $g) {
            $this->grupo_busyness[$g['id']] = [];
        }
        foreach ($aulas as $a) {
            $this->aula_busyness[$a['id']] = [];
        }
    }

    private function assignMateriaToGrupo($materia, $grupo, $docentes, $aulas) {
        $horas_restantes = (int)($materia['horas_semanales'] ?? 0);
        if ($horas_restantes <= 0) return true;

        $candidatos_ids = [];
        if (!empty($this->preferences[$materia['id']])) {
            foreach ($this->preferences[$materia['id']] as $did) {
                if (!in_array((string)$did, $candidatos_ids)) $candidatos_ids[] = (string)$did;
            }
        }

        foreach ($candidatos_ids as $docente_id) {
            $docente = null;
            foreach ($docentes as $d) { if ((string)$d['id'] === (string)$docente_id) { $docente = $d; break; } }
            if (!$docente) continue;

            $temp_horas = $horas_restantes;
            $possible = true;
            $booked_in_this_run = [];
            $used_days = [];
            
            while ($temp_horas > 0) {
                $duracion = ($temp_horas >= 2) ? 2 : 1;
                $slot = $this->findSlotAcrossWeekManual($materia, $grupo, $docente, $aulas, $duracion, $used_days);
                if (!$slot && $duracion == 2) {
                    $duracion = 1;
                    $slot = $this->findSlotAcrossWeekManual($materia, $grupo, $docente, $aulas, $duracion, $used_days);
                }

                if ($slot) {
                    $booked_in_this_run[] = array_merge($slot, ['duracion' => $duracion]);
                    $used_days[] = $slot['dia'];
                    $temp_horas -= $duracion;
                    $this->markTemporaryOcupation($docente, $grupo, $slot['aula'], $slot['dia'], $slot['hora'], $duracion);
                } else {
                    $possible = false;
                    break;
                }
            }

            if ($possible) {
                foreach ($booked_in_this_run as $b) {
                    $this->bookSlot($b['dia'], $b['hora'], $b['duracion'], $docente, $grupo, $b['aula'], $materia);
                }
                return true;
            } else {
                foreach ($booked_in_this_run as $b) {
                    $this->unmarkTemporaryOcupation($docente, $grupo, $b['aula'], $b['dia'], $b['hora'], $b['duracion']);
                }
            }
        }
        return false;
    }

    private function findSlotAcrossWeekManual($materia, $grupo, $docente, $aulas, $duracion, $exclude_days = []) {
        $dias = [1, 2, 3, 4, 5];
        shuffle($dias);
        
        $aulas_preferidas_ids = $docente['aulas_preferidas'] ?? [];
        $sorted_aulas = $aulas;
        if (!empty($aulas_preferidas_ids)) {
            usort($sorted_aulas, function($a, $b) use ($aulas_preferidas_ids) {
                $isA = in_array($a['id'], $aulas_preferidas_ids);
                $isB = in_array($b['id'], $aulas_preferidas_ids);
                if ($isA && !$isB) return -1;
                if (!$isA && $isB) return 1;
                return 0;
            });
        }

        $horas = range(7, 19);
        shuffle($horas);

        foreach ($dias as $dia) {
            if (in_array($dia, $exclude_days)) continue;
            foreach ($sorted_aulas as $aula) {
                foreach ($horas as $hora) {
                    if ($this->isAvailable($dia, $hora, $duracion, $docente, $grupo, [$aula])) {
                        return ['dia' => $dia, 'hora' => $hora, 'aula' => $aula];
                    }
                }
            }
        }
        return null;
    }

    private function markTemporaryOcupation($docente, $grupo, $aula, $dia, $hora, $duracion) {
        for ($h = $hora; $h < $hora + $duracion; $h++) {
            $this->docente_busyness[$docente['id']][$dia][$h] = true;
            $this->grupo_busyness[$grupo['id']][$dia][$h] = true;
            $this->aula_busyness[$aula['id']][$dia][$h] = true;
        }
        $this->docente_hours[$docente['id']]['diaria'][$dia] = ($this->docente_hours[$docente['id']]['diaria'][$dia] ?? 0) + $duracion;
        $this->docente_hours[$docente['id']]['semanal'] += $duracion;
    }

    private function unmarkTemporaryOcupation($docente, $grupo, $aula, $dia, $hora, $duracion) {
        for ($h = $hora; $h < $hora + $duracion; $h++) {
            unset($this->docente_busyness[$docente['id']][$dia][$h]);
            unset($this->grupo_busyness[$grupo['id']][$dia][$h]);
            unset($this->aula_busyness[$aula['id']][$dia][$h]);
        }
        $this->docente_hours[$docente['id']]['diaria'][$dia] -= $duracion;
        $this->docente_hours[$docente['id']]['semanal'] -= $duracion;
    }

    private function isAvailable($dia, $hora, $duracion, $docente, $grupo, $aulas) {
        if ($hora + $duracion > 21) return false;

        // Validaciones estrictas de TURNOS (mañana o tarde) según el cuatrimestre del grupo
        // Nota: Si estamos en Planeación, el $grupo que llega aquí ya trae su 'cuatrimestre' proyectado.
        $corte = (int)($this->config['turno_corte'] ?? 6);
        $turno = ((int)$grupo['cuatrimestre'] <= $corte) ? 'matutino' : 'vespertino';

        // Fuerza al matutino a NO pasar de las 16h, y al vespertino a NO empezar antes de las 11(o 13h)
        if ($turno == 'matutino' && $hora + $duracion > 16) return false;
        
        // Generalmente vespertino es de 14:00 o 13:00 en adelante. Si hora < 13 es bloque matutino.
        if ($turno == 'vespertino' && $hora < 13) return false;

        if (!$this->checkDocenteDisponibilidad($docente, $dia, $hora, $duracion)) return false;
        $carga_diaria = $this->docente_hours[$docente['id']]['diaria'][$dia] ?? 0;
        if ($carga_diaria + $duracion > (int)($docente['carga_max_diaria'] ?: 8)) return false;
        $carga_semanal = $this->docente_hours[$docente['id']]['semanal'];
        if ($carga_semanal + $duracion > (int)($docente['carga_max_semanal'] ?: 40)) return false;
        for ($h = $hora; $h < $hora + $duracion; $h++) {
            if (!empty($this->docente_busyness[$docente['id']][$dia][$h])) return false;
            if (!empty($this->grupo_busyness[$grupo['id']][$dia][$h])) return false;
        }
        return true;
    }

    private function checkDocenteDisponibilidad($docente, $dia, $hora, $duracion) {
        $disp = $docente['disponibilidad'] ?? [];
        if (empty($disp)) return true;
        foreach ($disp as $block) {
            if ((int)$block['dia'] == $dia) {
                if ($hora >= (int)$block['inicio'] && ($hora + $duracion) <= (int)$block['fin']) return true;
            }
        }
        return false;
    }

    private function bookSlot($dia, $hora, $duracion, $docente, $grupo, $aula, $materia) {
        for ($h = $hora; $h < $hora + $duracion; $h++) {
            $this->docente_busyness[$docente['id']][$dia][$h] = true;
            $this->grupo_busyness[$grupo['id']][$dia][$h] = true;
            $this->aula_busyness[$aula['id']][$dia][$h] = true;
        }
        $this->docente_hours[$docente['id']]['diaria'][$dia] = ($this->docente_hours[$docente['id']]['diaria'][$dia] ?? 0) + $duracion;
        $this->docente_hours[$docente['id']]['semanal'] += $duracion;
        $this->assigned_clases[] = [
            'dia' => $dia, 'hora_inicio' => $hora, 'duracion' => $duracion,
            'docente_id' => $docente['id'], 'grupo_id' => $grupo['id'], 'materia_id' => $materia['id'], 'aula_id' => $aula['id'],
            'docente_nombre' => $docente['nombre'], 'grupo_nombre' => $grupo['nombre'], 'materia_nombre' => $materia['nombre'], 'aula_nombre' => $aula['nombre']
        ];
    }

    private function saveToDatabase() {
        if (empty($this->assigned_clases)) return;
        $this->pdo->beginTransaction();
        try {
            // Limpiar horario previo (No borrar asesorías)
            $deleteSql = "DELETE FROM hor_clases WHERE carrera_id = ? AND es_asesoria = FALSE";
            $deleteParams = [$this->carrera_id];
            
            if ($this->hasPeriodoColumn) {
                $deleteSql .= " AND periodo = ?";
                $deleteParams[] = $this->current_period;
            }

            // APLICAR FILTROS DE ELIMINACIÓN (Si el usuario seleccionó grupos/profesores específicos)
            $targetGrupos = $this->filters['grupo_ids'] ?? [];
            $targetDocentes = $this->filters['docente_ids'] ?? [];

            if (count($targetGrupos) > 0) {
                $placeholders = implode(',', array_fill(0, count($targetGrupos), '?'));
                $deleteSql .= " AND grupo_id IN ($placeholders)";
                $deleteParams = array_merge($deleteParams, $targetGrupos);
            }

            if (count($targetDocentes) > 0) {
                $placeholders = implode(',', array_fill(0, count($targetDocentes), '?'));
                $deleteSql .= " AND docente_id IN ($placeholders)";
                $deleteParams = array_merge($deleteParams, $targetDocentes);
            }
            
            $this->pdo->prepare($deleteSql)->execute($deleteParams);

            if ($this->hasPeriodoColumn) {
                $insertSql = "INSERT INTO hor_clases (carrera_id, dia, hora_inicio, duracion, docente_id, grupo_id, materia_id, aula_id, periodo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            } else {
                $insertSql = "INSERT INTO hor_clases (carrera_id, dia, hora_inicio, duracion, docente_id, grupo_id, materia_id, aula_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            }

            $stmt = $this->pdo->prepare($insertSql);
            foreach ($this->assigned_clases as $c) {
                $params = [$this->carrera_id, $c['dia'], $c['hora_inicio'], $c['duracion'], $c['docente_id'], $c['grupo_id'], $c['materia_id'], $c['aula_id']];
                if ($this->hasPeriodoColumn) {
                    $params[] = $this->current_period;
                }
                $stmt->execute($params);
            }
            $this->pdo->commit();
        } catch (\Exception $e) { 
            if ($this->pdo->inTransaction()) $this->pdo->rollBack(); 
            throw $e; 
        }
    }
}
