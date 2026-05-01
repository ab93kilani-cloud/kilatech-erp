import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const wf = JSON.parse(fs.readFileSync(path, 'utf8'));

// 1. Add the Merge Node
const mergeNode = {
  "parameters": {
    "mode": "combine",
    "combinationMode": "mergeByIndice"
  },
  "id": "merge-node-id",
  "name": "🤝 Fusionneur d'Infos",
  "type": "n8n-nodes-base.merge",
  "typeVersion": 2.1,
  "position": [
    550,
    128
  ]
};

wf[0].nodes.push(mergeNode);

// 2. Rewire Connections
// Remove direct connections to Super Agent from Auditeur and Espion
delete wf[0].connections['🕵️ 1. L\'Auditeur'];
delete wf[0].connections['Espion'];

// Add new connections
wf[0].connections['🕵️ 1. L\'Auditeur'] = {
  "main": [
    [
      {
        "node": "🤝 Fusionneur d'Infos",
        "type": "main",
        "index": 0
      }
    ]
  ]
};

wf[0].connections['Espion'] = {
  "main": [
    [
      {
        "node": "🤝 Fusionneur d'Infos",
        "type": "main",
        "index": 1
      }
    ]
  ]
};

wf[0].connections['🤝 Fusionneur d'Infos'] = {
  "main": [
    [
      {
        "node": "🚀 Super Agent SEO",
        "type": "main",
        "index": 0
      }
    ]
  ]
};

// 3. Update Super Agent Prompt to use the merged data
const agentNode = wf[0].nodes.find(n => n.name === "🚀 Super Agent SEO");
if (agentNode) {
  agentNode.parameters.messages.values[0].content = "=Tu es l'Intelligence SEO Suprême de Kilatech.ma.\n\nPRODUIT : {{ $node[\"PRODUIT\"].json.data[0].name }}\nAUDIT : {{ JSON.stringify($json.audit || {}) }}\nINFOS MARCHÉ (Espion) : {{ JSON.stringify($json.organic || []) }}\n\nMISSION :\n1. Rédige une fiche produit HTML premium (H2, H3, P).\n2. Intègre naturellement 2-3 liens vers des catégories médicales.\n3. Crée un Titre H1 puissant incluant 'Casablanca' ou 'Maroc'.\n4. Crée une Meta-Description de 150 caractères.\n5. Crée un post LinkedIn et un post Facebook.\n\nCONSIGNES ULTRA-STRICTES :\n- RÉPONDS UNIQUEMENT EN JSON VALIDE.\n- AUCUNE balise <img>.\n- AUCUN bloc markdown.\n- Langue : Français parfait.\n\nRÉPONDS EN JSON :\n{\n  \"h1_title\": \"...\",\n  \"meta_description\": \"...\",\n  \"html_content\": \"...\",\n  \"linkedin_post\": \"...\",\n  \"facebook_post\": \"...\"\n}";
}

fs.writeFileSync(path, JSON.stringify(wf, null, 2));
console.log('✅ Merge node added and connections rewired via Terminal.');
