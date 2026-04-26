const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_FINAL_REPAIRED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Update Architect
let architectNode = nodes.find(n => n.name === 'Architect');
if (architectNode) {
    architectNode.parameters.prompt = `=Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la structure parfaite pour optimiser ce produit médical au Maroc.

DONNÉES DU PRODUIT : {{ JSON.stringify($json.product) }}
AUDIT TECHNIQUE : {{ $json.text }}

TU DOIS RÉPONDRE UNIQUEMENT EN FORMAT JSON AVEC CES 5 SECTIONS :
1. "plan_h1_h2" : Liste des titres optimisés.
2. "instructions_designer" : Comment mettre en forme le texte.
3. "mots_cles_experts" : Liste des mots techniques à utiliser.
4. "meta_description" : La méta-description recommandée.
5. "image_url" : Recopie ici l'URL de l'image du produit ({{ $json.product.main_image_url || $json.product.images[0].url }})

Ne fais pas de phrases d'introduction, réponds uniquement en JSON.`;
}

// 2. Update Dossier node to capture image_url from Architect
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

// Get the image URL from Architect output (which is JSON)
let imageUrl = 'N/A';
try {
    const archResult = JSON.parse($('Architect').first().json.text);
    imageUrl = archResult.image_url || 'N/A';
} catch (e) {}

return {
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    analyste: getSafe("📊 10. L'Analyste"),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef'),
    image_url: imageUrl
};`;
    dossierNode.parameters.mode = 'runOnceForAllItems';
}

// 3. Update Finalizer to use the image
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication de Kilatech.ma. 
MISSION : Produire l'ITEM ULTIME en fusionnant le MEILLEUR de chaque expert. 

VOICI VOTRE DOSSIER COMPLET :
- IMAGE DU PRODUIT : {{ $json.image_url }}
- BASE RÉDACTIONNELLE : {{ $json.redacteur }}
- OPTIMISATION VISUELLE (Gras/Listes) : {{ $json.designer }}
- MAILLAGE STRATÉGIQUE (Liens) : {{ $json.tisseur }}
- STRATÉGIE SOCIALE : {{ $json.cm }}
- ANALYSE CRITIQUE : {{ $json.analyste }}
- VALIDATION RÉDACTEUR CHEF : {{ $json.redacteur_chef }}

CONSIGNES :
1. COMMENCE l'article par l'image du produit : <img src="{{ $json.image_url }}" alt="Optimise l'ALT tag avec le nom du produit et Kilatech" style="max-width:100%; height:auto; border-radius:8px;">
2. FUSIONNE le texte du rédacteur avec le gras du designer et les liens du tisseur.
3. AJOUTE la section promotion sociale à la fin (<hr>).
4. Renvoie UNIQUEMENT le code HTML propre.`;
}

fs.writeFileSync('WORKFLOW_WITH_IMAGES.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_WITH_IMAGES.json created');
