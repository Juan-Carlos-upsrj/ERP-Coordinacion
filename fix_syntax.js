
const fs = require('fs');
const file = 'c:\\Coordinacion\\app\\views\\pages\\horarios.php';
let content = fs.readFileSync(file, 'utf8');

// The corrupted pattern from PS payload is literally: ` `<option
content = content.replaceAll('` `<option', '`<option');

// The corrupted string interpolation endings
content = content.replaceAll('`+esc(p)+`</option>`).join(\'\')}', '>${esc(p)}</option>`).join(\'\')}');
content = content.replaceAll('`+d+`</option>`).join(\'\')}', '>${d}</option>`).join(\'\')}');
content = content.replaceAll('`+esc(d.nombre)+`</option>`).join(\'\')}', '>${esc(d.nombre)}</option>`).join(\'\')}');
content = content.replaceAll('`+esc(g.nombre)+` (C`+g.cuatrimestre+`)</option>`).join(\'\')}', '>${esc(g.nombre)} (C${g.cuatrimestre})</option>`).join(\'\')}');
content = content.replaceAll('`+esc(a.nombre)+(a.edificio?\' \\u00b7 \'+esc(a.edificio):\'\')+`</option>`).join(\'\')}', '>${esc(a.nombre)}${a.edificio?\' \u00b7 \'+esc(a.edificio):\'\'}</option>`).join(\'\')}');

content = content.replaceAll('\'\'+` `<button', '`<button');
content = content.replaceAll('</button>`+\'\'', '</button>`');

content = content.replaceAll('body.innerHTML = ` ', 'body.innerHTML = `');
content = content.replaceAll('</select></div>` ;', '</select></div>`;');

fs.writeFileSync(file, content, 'utf8');
console.log('Replacements applied successfully.');
