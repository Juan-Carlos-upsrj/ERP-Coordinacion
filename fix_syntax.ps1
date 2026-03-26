
$file = "c:\Coordinacion\app\views\pages\horarios.php"
$content = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)

# Replace the broken backticks in the map functions
$content = $content.Replace("` `<option", "`<option")
$content = $content.Replace("`+esc(p)+`</option>`).join('')}", ">`${esc(p)}</option>`).join('')}")
$content = $content.Replace("`+d+`</option>`).join('')}", ">`${d}</option>`).join('')}")
$content = $content.Replace("`+esc(d.nombre)+`</option>`).join('')}", ">`${esc(d.nombre)}</option>`).join('')}")
$content = $content.Replace("`+esc(g.nombre)+` (C`+g.cuatrimestre+`)</option>`).join('')}", ">`${esc(g.nombre)} (C`${g.cuatrimestre})</option>`).join('')}")
$content = $content.Replace("`+esc(a.nombre)+(a.edificio?' \u00b7 '+esc(a.edificio):'')+`</option>`).join('')}", ">`${esc(a.nombre)}${a.edificio?' · '+esc(a.edificio):''}</option>`).join('')}")

$content = $content.Replace("''+` `<button", "`<button")
$content = $content.Replace("</button>`+''", "</button>`")

$content = $content.Replace("body.innerHTML = ` ", "body.innerHTML = `")
$content = $content.Replace("</select></div>` ;", "</select></div>`;")

[System.IO.File]::WriteAllText($file, $content, [System.Text.Encoding]::UTF8)
Write-Host "Replacements applied."
