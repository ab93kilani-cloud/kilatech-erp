import fs from 'fs';

const sourcePath = 'backend/WORKFLOW_MASTER_KILATECH.json';
const targetPath = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';

const content = fs.readFileSync(sourcePath, 'utf8');
const originalWf = JSON.parse(content)[0];

// Define the new nodes for the Optimized Workflow
const optimizedNodes = [
  // 1. Trigger
  originalWf.nodes.find(n => n.type === 'n8n-nodes-base.scheduleTrigger' || n.name.includes('Pilote')),
  
  // 2. Fetch Products
  originalWf.nodes.find(n => n.name === 'GSC - Get Products (ERP)'),
  
  // 3. Split Batch
  originalWf.nodes.find(n => n.name === 'PRODUIT'),
  
  // 4. Audit
  originalWf.nodes.find(n => n.name.includes('Auditeur')),
  
  // 5. Search Competition
  originalWf.nodes.find(n => n.name === 'Espion'),

  // 6. SUPER AGENT: L'Intelligence Kilatech
  {
    "parameters": {
      "model": "gpt-4o",
      "prompt": "=Tu es l'Intelligence SEO Suprême de Kilatech.ma.\n\nPRODUIT : {{ $node[\"PRODUIT\"].json.name }}\nINFOS MARCHÉ : {{ JSON.stringify($json.organic || []) }}\n\nMISSION :\n1. Rédige une fiche produit HTML premium (H2, H3, P).\n2. Intègre naturellement 2-3 liens vers des catégories médicales.\n3. Crée un Titre H1 puissant incluant 'Casablanca' ou 'Maroc'.\n4. Crée une Meta-Description de 150 caractères.\n5. Crée un post LinkedIn et un post Facebook.\n\nCONSIGNES ULTRA-STRICTES :\n- AUCUNE balise <img>.\n- AUCUN bloc markdown (pas de ```html).\n- AUCUN conseil technique dans le texte.\n- Langue : Français parfait ('au Maroc').\n\nRÉPONDS UNIQUEMENT EN JSON :\n{\n  \"h1_title\": \"...\",\n  \"meta_description\": \"...\",\n  \"html_content\": \"...\",\n  \"linkedin_post\": \"...\",\n  \"facebook_post\": \"...\"\n}",
      "options": { "maxTokens": 4000, "temperature": 0.7 }
    },
    "id": "super-agent-id",
    "name": "🚀 Super Agent SEO",
    "type": "n8n-nodes-base.openAi",
    "typeVersion": 1,
    "position": [800, 128],
    "credentials": { "openAiApi": { "id": "DmSfvKlB2CMFj7eE", "name": "OpenAi account 2" } }
  },

  // 7. PREPA TRANSFERT
  {
    "parameters": {
      "jsCode": "const data = $json;\nreturn {\n  product_id: $('PRODUIT').first().json.id,\n  seo_title: data.h1_title,\n  seo_description: data.meta_description,\n  seo_content: data.html_content,\n  social_linkedin_post: data.linkedin_post,\n  social_facebook_post: data.facebook_post,\n  seo_score: 95\n};"
    },
    "id": "prepa-v2-id",
    "name": "📦 Nettoyeur V2",
    "type": "n8n-nodes-base.code",
    "typeVersion": 2,
    "position": [1100, 128]
  },

  // 8. DIRECTEUR (Update App)
  originalWf.nodes.find(n => n.name.includes('Directeur')),

  // 9. MANAGER (WhatsApp Notification)
  originalWf.nodes.find(n => n.name.includes('Manager'))
].filter(n => n !== undefined);

// Define Connections
const optimizedConnections = {
  "⏰ Pilote Automatique": { "main": [[{ "node": "GSC - Get Products (ERP)", "type": "main", "index": 0 }]] },
  "GSC - Get Products (ERP)": { "main": [[{ "node": "PRODUIT", "type": "main", "index": 0 }]] },
  "PRODUIT": { "main": [[], [{ "node": "🕵️ 1. L'Auditeur", "type": "main", "index": 0 }, { "node": "Espion", "type": "main", "index": 0 }]] },
  "🕵️ 1. L'Auditeur": { "main": [[{ "node": "🚀 Super Agent SEO", "type": "main", "index": 0 }]] },
  "Espion": { "main": [[{ "node": "🚀 Super Agent SEO", "type": "main", "index": 0 }]] },
  "🚀 Super Agent SEO": { "main": [[{ "node": "📦 Nettoyeur V2", "type": "main", "index": 0 }]] },
  "📦 Nettoyeur V2": { "main": [[{ "node": "🏢 11. Le Directeur", "type": "main", "index": 0 }]] },
  "🏢 11. Le Directeur": { "main": [[{ "node": "📱 12. Le Manager", "type": "main", "index": 0 }]] }
};

const newWorkflow = {
  name: "Kilatech ECONOMY - Ultra Optimized (3 Agents)",
  nodes: optimizedNodes,
  connections: optimizedConnections,
  settings: originalWf.settings,
  staticData: null,
  meta: originalWf.meta,
  tags: []
};

fs.writeFileSync(targetPath, JSON.stringify([newWorkflow], null, 2));
console.log('✅ New Optimized Workflow "V2 ECONOMY" created successfully.');
