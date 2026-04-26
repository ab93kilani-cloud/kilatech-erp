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
}

let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication de Kilatech.ma. MISSION : Produire le contenu HTML final parfait en fusionnant les apports de chaque expert.

VOICI LE DOSSIER COMPLET :
1. DRAFT DU RÉDACTEUR : {{ $json.redacteur }}
2. STYLE & DESIGN (Gras, listes) : {{ $json.designer }}
3. MAILLAGE INTERNE (Liens) : {{ $json.tisseur }}
4. PROMOTION SOCIALE : {{ $json.cm }}
5. CORRECTIONS DE L'ANALYSTE : {{ $json.analyste }}
6. VALIDATION RÉDACTEUR CHEF : {{ $json.redacteur_chef }}

CONSIGNES :
- Combine le texte du rédacteur avec le gras du designer et les liens du tisseur.
- Applique les corrections demandées par l'analyste.
- Ajoute la section promotion sociale à la fin.
- Renvoie UNIQUEMENT le code HTML propre (sans balises <html>/<body>/head).`;
}

fs.writeFileSync('SUPER_FUSION_PERFECT.json', JSON.stringify(wf));
console.log('SUCCESS: SUPER_FUSION_PERFECT.json created');
