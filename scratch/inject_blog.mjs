
import fs from 'fs';
const path = 'backend/WORKFLOW_KILATECH_V2_ECONOMY.json';
const workflow = JSON.parse(fs.readFileSync(path, 'utf8'));

// 1. Create Le Blogueur Node
const blogueurNode = {
  "parameters": {
    "modelId": "gpt-4o",
    "messages": {
      "values": [
        {
          "content": "=Tu es le Rédacteur en Chef du Blog Kilatech.ma.\n\nPRODUIT : {{ $node[\"PRODUIT\"].json.name }}\nSEO TITLE : {{ $node[\"🚀 Super Agent SEO\"].json.seo_title }}\n\nMISSION :\nRédige un article de blog EXPERT de 1000 mots minimum pour booster l'autorité du site.\n\nSTRUCTURE DU JSON ATTENDU :\n{\n  \"title\": \"Titre H1 accrocheur et SEO\",\n  \"content\": \"Texte HTML complet (1000 mots, H2, H3, gras, listes)\",\n  \"meta_title\": \"Titre SEO\",\n  \"meta_description\": \"Description de 150 caractères\",\n  \"category\": \"blog\",\n  \"status\": \"published\"\n}\n\nCONSIGNES :\n1. Ton informatif, médical et professionnel.\n2. Inclus des cas d'usage au Maroc.\n3. Optimise pour les mots-clés : {{ $node[\"🚀 Super Agent SEO\"].json.seo_title }}.\n\nRÉPONDS UNIQUEMENT EN JSON PUR.",
          "role": "system"
        }
      ]
    },
    "options": {
      "responseFormat": "json_object"
    }
  },
  "id": "blogueur-node-id",
  "name": "✍️ 13. Le Blogueur",
  "type": "n8n-nodes-base.openAiChatModel",
  "typeVersion": 1,
  "position": [
    1200,
    128
  ]
};

// 2. Create Publication Blog Node
const pubBlogNode = {
  "parameters": {
    "method": "POST",
    "url": "https://app.kilatech.ma/api/seo-v2/articles",
    "sendQuery": true,
    "queryParameters": {
      "parameters": [
        {
          "name": "api_token",
          "value": "KILA-SEO-PROD-2026-X9"
        }
      ]
    },
    "sendBody": true,
    "bodyParameters": {
      "parameters": [
        {
          "name": "title",
          "value": "={{ $node[\"✍️ 13. Le Blogueur\"].json.message.content.title }}"
        },
        {
          "name": "content",
          "value": "={{ $node[\"✍️ 13. Le Blogueur\"].json.message.content.content }}"
        },
        {
          "name": "meta_title",
          "value": "={{ $node[\"✍️ 13. Le Blogueur\"].json.message.content.meta_title }}"
        },
        {
          "name": "meta_description",
          "value": "={{ $node[\"✍️ 13. Le Blogueur\"].json.message.content.meta_description }}"
        },
        {
          "name": "product_id",
          "value": "={{ $node[\"PRODUIT\"].json.id }}"
        },
        {
          "name": "status",
          "value": "published"
        },
        {
          "name": "category",
          "value": "blog"
        }
      ]
    },
    "options": {}
  },
  "id": "pub-blog-node-id",
  "name": "📰 14. Publication Blog",
  "type": "n8n-nodes-base.httpRequest",
  "typeVersion": 4.1,
  "position": [
    1400,
    128
  ]
};

// Add nodes to workflow (if not already there)
if (!workflow[0].nodes.find(n => n.name.includes('Blogueur'))) {
    workflow[0].nodes.push(blogueurNode);
    workflow[0].nodes.push(pubBlogNode);

    // Add connection from Le Directeur to Le Blogueur
    workflow[0].connections["🏢 11. Le Directeur"].main[0].push({
      "node": "✍️ 13. Le Blogueur",
      "type": "main",
      "index": 0
    });

    // Add connection from Le Blogueur to Publication Blog
    workflow[0].connections["✍️ 13. Le Blogueur"] = {
      "main": [
        [
          {
            "node": "📰 14. Publication Blog",
            "type": "main",
            "index": 0
          }
        ]
      ]
    };

    fs.writeFileSync(path, JSON.stringify(workflow, null, 2));
    console.log('Workflow updated with Blog nodes!');
} else {
    console.log('Blog nodes already present.');
}
