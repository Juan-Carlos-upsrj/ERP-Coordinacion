const { NodeSSH } = require('c:\\AdminUPSRJ\\node_modules\\node-ssh');
const ssh = new NodeSSH();
const fs = require('fs');
const path = require('path');

(async () => {
    try {
        await ssh.connect({
            host: 'gestionacademica.tailaf0046.ts.net',
            username: 'yeici',
            password: '03yeierpupsrj03'
        });

        const localFile = path.join(__dirname, 'check_constraint.sql');
        const remoteFile = '/tmp/check_constraint.sql';
        await ssh.putFile(localFile, remoteFile);

        const res = await ssh.execCommand(`sudo -S -u postgres psql -d erp_academico -f ${remoteFile}`, { 
            stdin: '03yeierpupsrj03\n' 
        });
        console.log('STDOUT:', res.stdout);

        ssh.dispose();
    } catch (err) {
        console.error(err);
    }
})();
