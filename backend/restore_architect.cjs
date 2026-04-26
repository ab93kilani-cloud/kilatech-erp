const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_REPARATION_CONFIRMEE.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. RESTORE Architect to its original stable state (as requested by user)
let architectNode = nodes.find(n => n.name === 'Architect');
if (architectNode) {
    architectNode.parameters.prompt = `Tu es l'Architecte SEO Senior de Kilatech.ma. Ta mission est de concevoir la structure parfaite pour optimiser ce produit médical au Maroc.

AUDIT TECHNIQUE : {{ $json.text }}

TU DOIS RÉPONDRE UNIQUEMENT EN FORMAT JSON AVEC CES 4 SECTIONS :
1. "plan_h1_h2" : Liste des titres optimisés.
2. "instructions_designer" : Comment mettre en forme le texte.
3. "mots_cles_experts" : Liste des mots techniques à utiliser.
4. "meta_description" : La méta-description recommandée.

Ne fais pas de phrases d'introduction, réponds uniquement en JSON.`;
}

// 2. Update Dossier node to get image DIRECTLY from Auditor instead of Architect
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

// Get image URL directly from Auditor node
let imageUrl = 'N/A';
try {
    const auditor = $("1. L'Auditeur");
    if (auditor && auditor.first()) {
        imageUrl = auditor.first().json.product.main_image_url || auditor.first().json.product.images[0].url || 'N/A';
    }
} catch (e) {}

return {
    product_id: $('PRODUIT').first().json.id,
    product_name: $('PRODUIT').first().json.name,
    redacteur: getSafe('Redacteur'),
    designer: getSafe('🎨 5. Le Designer'),
    tisseur: getSafe('🔗 6. Le Tisseur'),
    cm: getSafe('📢 9. Le CM'),
    redacteur_chef: getSafe('⚖️ 8. Le Rédacteur en Chef'),
    image_url: imageUrl
};`;
}

fs.writeFileSync('WORKFLOW_ARCHITECTE_REPARE.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_ARCHITECTE_REPARE.json created');
