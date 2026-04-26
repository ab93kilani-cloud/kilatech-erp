const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_FINAL_REPAIRED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

let dossierNode = nodes.find(n => n.name === 'Dossier de Redaction Final');
if (dossierNode) {
    dossierNode.parameters.jsCode = `return {
    redacteur: $('Redacteur').first().json.text || 'N/A',
    designer: $('🎨 5. Le Designer').first().json.text || 'N/A',
    tisseur: $('🔗 6. Le Tisseur').first().json.text || 'N/A',
    cm: $('📢 9. Le CM').first().json.text || 'N/A',
    analyste: $("📊 10. L'Analyste").first().json.text || 'N/A',
    redacteur_chef: $('⚖️ 8. Le Rédacteur en Chef').first().json.text || 'N/A'
};`;
    // Force run once for all items
    dossierNode.parameters.mode = 'runOnceForAllItems';
}

let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication de Kilatech.ma. 
MISSION CRITIQUE : Produire l'ITEM ULTIME en fusionnant le MEILLEUR de chaque expert. 

VOICI VOTRE DOSSIER COMPLET :
1. BASE RÉDACTIONNELLE : {{ $json.redacteur }}
2. OPTIMISATION VISUELLE (Gras/Listes) : {{ $json.designer }}
3. MAILLAGE STRATÉGIQUE (Liens) : {{ $json.tisseur }}
4. STRATÉGIE SOCIALE : {{ $json.cm }}
5. ANALYSE CRITIQUE : {{ $json.analyste }}
6. DERNIÈRE VALIDATION : {{ $json.redacteur_chef }}

CONSIGNES DE FUSION :
- Ne choisis pas entre les versions, FUSIONNE-LES.
- Garde l'intégralité du texte riche, les balises <strong> du designer ET les liens <a> du tisseur.
- Intègre les posts sociaux du CM à la fin de l'article avec une séparation <hr>.
- Respecte les corrections de l'analyste si des erreurs sont signalées.
- Renvoie UNIQUEMENT le code HTML du corps de texte (pas de <html>, pas de <body>).`;
}

fs.writeFileSync('SUPER_FUSION_ULTIME.json', JSON.stringify(wf));
console.log('SUCCESS: SUPER_FUSION_ULTIME.json created with RunOnce mode');
