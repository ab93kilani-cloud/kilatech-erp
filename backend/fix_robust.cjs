const fs = require('fs');
const content = fs.readFileSync('REPAIR_AFTER_DELETE.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

let dossierNode = nodes.find(n => n.name === 'Dossier de Redaction Final');
if (dossierNode) {
    dossierNode.parameters.jsCode = `const getSafe = (nodeName) => {
    try {
        const node = $(nodeName);
        if (node && node.first()) {
            return node.first().json.text || 'N/A';
        }
    } catch (e) {}
    return 'N/A';
};

return {
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    analyste: getSafe("📊 10. L'Analyste"),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef')
};`;
    dossierNode.parameters.mode = 'runOnceForAllItems';
}

fs.writeFileSync('ROBUST_WORKFLOW.json', JSON.stringify(wf));
console.log('SUCCESS: ROBUST_WORKFLOW.json created');
