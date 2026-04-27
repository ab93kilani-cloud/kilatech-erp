const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_DIRECT.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Fix Finalizer Image Logic
let finaliseurNode = nodes.find(n => n.name === 'Le Finaliseur');
if (finaliseurNode) {
    finaliseurNode.parameters.responses.values[0].content = `=Tu es le Directeur de Publication Kilatech.
MISSION : Produire 3 éléments SEO séparés.

DONNÉES :
- NOM : {{ $("PRODUIT").first().json.name }}
- IMAGE_PATH : {{ $("PRODUIT").first().json.image_path }}
- DESIGNER : {{ $node["🎨 5. Le Designer"].json.text }}
- TEXTE : {{ $node["Dossier de Redaction Final"].json.redacteur }}

CONSIGNES :
1. Utilise l'URL suivante pour l'image : https://app.kilatech.ma/storage/{{ $("PRODUIT").first().json.image_path }}
2. Si IMAGE_PATH est vide, n'affiche pas de balise <img>.
3. Réponds UNIQUEMENT en JSON :
{
  "seo_title": "...",
  "seo_description": "...",
  "seo_content": "<img src=\\"https://app.kilatech.ma/storage/{{ $("PRODUIT").first().json.image_path }}\\" style=\\"width:100%; border-radius:20px; box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1); margin-bottom: 2rem;\\"> <h1>...</h1> ..."
}`;
}

fs.writeFileSync('WORKFLOW_IMAGE_FIXED.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_IMAGE_FIXED.json created');
