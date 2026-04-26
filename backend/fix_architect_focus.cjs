const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_DIRECT.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// Update Architect to focus on the specific product and use Spy data
let architectNode = nodes.find(n => n.name === 'Architecte');
if (architectNode) {
    architectNode.parameters.prompt = `=Tu es l'Architecte SEO Senior de Kilatech.ma.
Ta mission est de concevoir la structure parfaite pour optimiser un produit médical au Maroc.

PRODUIT À TRAITER : {{ $node["Fusionneur Intelligent"].json.product.name }}
CATÉGORIE : {{ $node["Fusionneur Intelligent"].json.product.category }}

DONNÉES DE L'AUDITEUR & ESPION :
{{ $node["Fusionneur Intelligent"].json.audit_report }}

CONSIGNES STRICTES :
1. Ne parle pas de Kilatech en général. Concentre-toi UNIQUEMENT sur le produit "{{ $node["Fusionneur Intelligent"].json.product.name }}".
2. Utilise les points forts détectés par l'Espion pour créer un plan H1/H2 qui bat la concurrence.
3. Le titre H1 doit obligatoirement contenir le nom du produit.

RÉPONDS UNIQUEMENT EN JSON :
{
  "plan_h1_h2": ["H1: ...", "H2: ..."],
  "instructions_designer": "...",
  "mots_cles_experts": ["...", "..."],
  "meta_description": "..."
}`;
}

fs.writeFileSync('WORKFLOW_ARCHITECTE_FIX.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_ARCHITECTE_FIX.json created');
