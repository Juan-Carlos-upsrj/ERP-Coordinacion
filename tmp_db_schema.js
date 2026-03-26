const { NodeSSH } = require('c:/AdminUPSRJ/node_modules/node-ssh');
const ssh = new NodeSSH();

async function runSQL() {
    try {
        await ssh.connect({ host: '100.115.216.95', username: 'yeici', password: '03yeierpupsrj03', port: 22 });
        const sql = `
            CREATE TABLE IF NOT EXISTS hor_solicitudes_justificantes (
                id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                alumno_nombre   VARCHAR(255) NOT NULL,
                matricula       VARCHAR(50) NOT NULL,
                fecha_ausencia  DATE NOT NULL,
                motivo          TEXT NOT NULL,
                archivo_url     VARCHAR(500),
                estado          VARCHAR(50) DEFAULT 'pendiente',
                creado_en       TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
            CREATE INDEX IF NOT EXISTS idx_solicitudes_estado ON hor_solicitudes_justificantes(estado);
            CREATE INDEX IF NOT EXISTS idx_solicitudes_matricula ON hor_solicitudes_justificantes(matricula);
            GRANT ALL PRIVILEGES ON TABLE hor_solicitudes_justificantes TO admin_erp;
        `;
        const cmd = `echo '03yeierpupsrj03' | sudo -S -u postgres psql -d erp_academico -c "${sql.replace(/\n/g, ' ')}"`;
        const result = await ssh.execCommand(cmd);
        console.log("OUT:", result.stdout);
        console.log("ERR:", result.stderr);
    } catch(e) {
        console.error(e);
    } finally {
        ssh.dispose();
    }
}
runSQL();
