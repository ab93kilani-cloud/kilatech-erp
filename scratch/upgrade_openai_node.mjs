import fs from 'fs';

const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const wf = JSON.parse(fs.readFileSync(path, 'utf8'));

// Replace the OpenAI node with a Chat Model version
const oldNodeIndex = wf[0].nodes.findIndex(n => n.name === "🚀 Super Agent SEO");
if (oldNodeIndex !== -1) {
  wf[0].nodes[oldNodeIndex] = {
    "parameters": {
      "modelId": "gpt-4o",
      "messages": {
        "values": [
          {
            "content": "=Tu es l'Intelligence SEO Suprême de Kilatech.ma.\n\nPRODUIT : {{ $node[\"PRODUIT\"].json.data[0].name }}\nINFOS MARCHÉ : {{ JSON.stringify($json.organic || []) }}\n\nMISSION :\n1. Rédige une fiche produit HTML premium (H2, H3, P).\n2. Intègre naturellement 2-3 liens vers des catégories médicales.\n3. Crée un Titre H1 puissant incluant 'Casablanca' ou 'Maroc'.\n4. Crée une Meta-Description de 150 caractères.\n5. Crée un post LinkedIn et un post Facebook.\n\nCONSIGNES ULTRA-STRICTES :\n- AUCUNE balise <img>.\n- AUCUN bloc markdown (pas de ```html).\n- AUCUN conseil technique dans le texte.\n- Langue : Français parfait ('au Maroc').\n\nRÉPONDS UNIQUEMENT EN JSON :\n{\n  \"h1_title\": \"...\",\n  \"meta_description\": \"...\",\n  \"html_content\": \"...\",\n  \"linkedin_post\": \"...\",\n  \"facebook_post\": \"...\"\n}"
          }
        ]
      },
      "options": {
        "maxTokens": 4000,
        "temperature": 0.7
      }
    },
    "id": "super-agent-id",
    "name": "🚀 Super Agent SEO",
    "type": "n8n-nodes-base.openAi",
    "typeVersion": 1.1,
    "position": [
      800,
      128
    ],
    "credentials": {
      "openAiApi": {
        "id": "DmSfvKlB2CMFj7eE",
        "name": "OpenAi account 2"
      }
    }
  };
}

fs.writeFileSync(path, JSON.stringify(wf, null, 2));
console.log('✅ Super Agent upgraded to Chat Model compatibility.');
