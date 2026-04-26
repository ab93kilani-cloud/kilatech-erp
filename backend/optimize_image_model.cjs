const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_ARCHITECTE_REPARE.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Upgrade Designer to o4-mini and give Image Optimization mission
let designerNode = nodes.find(n => n.name === '🎨 5. Le Designer');
if (designerNode) {
    designerNode.parameters.model = 'o4-mini'; // Choosing o4-mini from user list for best results
    designerNode.parameters.prompt = `=Tu es le Designer visuel de Kilatech.ma.
MISSION : Optimiser l'apparence de l'article et surtout de l'IMAGE DU PRODUIT.

DONNÉES : {{ $json.text }}
URL IMAGE : {{ $node["Dossier de Redaction Final"].json.image_url }}

CONSIGNES :
1. Crée une balise ALT SEO parfaite pour cette image (incluant le nom du produit et Kilatech).
2. Définis le style CSS pour que l'image soit "Premium" (shadow, rounded, etc.).
3. Indique où placer les éléments de mise en forme (gras, listes) pour que ce soit beau.

Réponds en format JSON : {"image_alt": "...", "css_style": "...", "layout_instructions": "..."}`;
}

// 2. Update Finalizer to apply the optimized image strategy
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication de Kilatech.ma. 
MISSION : Produire l'ITEM ULTIME avec une IMAGE OPTIMISÉE.

DOSSIER :
- IMAGE : {{ $node["Dossier de Redaction Final"].json.image_url }}
- DESIGNER (o4-mini) : {{ $node["🎨 5. Le Designer"].json.text }}
- TEXTE : {{ $node["Dossier de Redaction Final"].json.redacteur }}

CONSIGNES IMAGE :
Utilise l'URL et l'ALT tag recommandé par le Designer pour créer la balise image parfaite. 
Exemple : <img src="..." alt="..." style="...">

CONSIGNES TEXTE :
Applique le style et les liens du tisseur. 
Rends le tout harmonieux et Premium.`;
}

fs.writeFileSync('WORKFLOW_IMAGE_OPTIMIZED.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_IMAGE_OPTIMIZED.json created with o4-mini');
