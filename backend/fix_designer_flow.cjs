const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_IMAGE_OPTIMIZED.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Designer to use an UPSTREAM node (PRODUIT) instead of a downstream one (Dossier)
let designerNode = nodes.find(n => n.name === '🎨 5. Le Designer');
if (designerNode) {
    designerNode.parameters.prompt = `=Tu es le Designer visuel de Kilatech.ma.
MISSION : Optimiser l'apparence de l'article et surtout de l'IMAGE DU PRODUIT.

DONNÉES : {{ $json.text }}
URL IMAGE : {{ $("PRODUIT").first().json.images[0]?.url || "https://api.kilatech.ma/storage/placeholder.jpg" }}

CONSIGNES :
1. Crée une balise ALT SEO parfaite pour cette image (incluant le nom du produit et Kilatech).
2. Définis le style CSS pour que l'image soit "Premium" (shadow, rounded, etc.).
3. Indique où placer les éléments de mise en forme (gras, listes) pour que ce soit beau.

Réponds en format JSON : {"image_alt": "...", "css_style": "...", "layout_instructions": "..."}`;
}

fs.writeFileSync('WORKFLOW_DESIGNER_FIXED.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_DESIGNER_FIXED.json created');
