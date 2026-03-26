const fs = require('fs');
let text = fs.readFileSync('c:/Coordinacion/app/views/pages/horarios.php', 'utf8');
const originalText = text;

// Fix standard ones like value="Da" 'selected':''}>>Da
text = text.replace(/''\}>>/g, "''}>");

if (text !== originalText) {
    fs.writeFileSync('c:/Coordinacion/app/views/pages/horarios.php', text, 'utf8');
    console.log('Fixed >> typos');
} else {
    console.log('No >> typos found');
}