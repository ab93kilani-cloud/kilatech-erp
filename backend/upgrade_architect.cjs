const fs = require('fs');
const content = fs.readFileSync('WORKFLOW_DIRECT.json', 'utf8');
let wf = JSON.parse(content);
let nodes = wf[0].nodes;

// 1. Find the Architect and Change its Type and Model
let architectNode = nodes.find(n => n.name === 'Architecte');
if (architectNode) {
    // Switch to Chat Model node type (simulated by updating parameters for n8n OpenAI Chat Node)
    architectNode.type = 'n8n-nodes-base.openAi';
    architectNode.typeVersion = 1.1; // Ensure it's the newer version that supports Chat
    architectNode.parameters.resource = 'chat';
    architectNode.parameters.operation = 'conclude'; // Or message
    architectNode.parameters.model = 'o4-mini'; // Use the user's preferred smart model
    
    architectNode.parameters.messages = {
        values: [
          {
            role: 'system',
            content: 'Tu es l\'Architecte SEO Senior. Ta mission est de structurer un article pour un produit PRÉCIS. Tu dois utiliser les données de l\'Auditeur/Espion pour battre la concurrence.'
          },
          {
            role: 'user',
            content: `=NOM DU PRODUIT : {{ $node["Fusionneur Intelligent"].json.product.name }}
RAPPORT DE L'ESPION : {{ $node["Fusionneur Intelligent"].json.audit_report }}

CONSIGNES :
1. Crée un plan H1/H2 qui parle UNIQUEMENT de "{{ $node["Fusionneur Intelligent"].json.product.name }}".
2. Ne fais pas de généralités sur Kilatech.ma.
3. Le H1 doit être : "Guide Complet : {{ $node["Fusionneur Intelligent"].json.product.name }} au Maroc".

RÉPONDS UNIQUEMENT EN JSON :
{
  "plan_h1_h2": ["H1: ...", "H2: ..."],
  "instructions_designer": "...",
  "mots_cles_experts": ["...", "..."],
  "meta_description": "..."
}`
          }
        ]
    };
    // Clean up old Completion parameters
    delete architectNode.parameters.prompt;
}

fs.writeFileSync('WORKFLOW_INTEL_ARCHITECT.json', JSON.stringify(wf));
console.log('SUCCESS: WORKFLOW_INTEL_ARCHITECT.json created');
