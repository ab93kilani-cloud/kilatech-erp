

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';
const MASTER_WF_ID = 'Rbx87xuCY2i1VVBQ';

async function request(endpoint, method, payload = null) {
  const options = {
    method,
    headers: {
      'X-N8N-API-KEY': API_KEY,
      'Content-Type': 'application/json'
    }
  };
  if (payload) options.body = JSON.stringify(payload);
  const res = await fetch(`${N8N_URL}${endpoint}`, options);
  const text = await res.text();
  if (!res.ok) throw new Error(`${method} ${endpoint} failed: ${res.status} ${text}`);
  return text ? JSON.parse(text) : null;
}

async function main() {
  try {
    console.log("Creating the 'Dossier de Rédaction' (Humanizer for AI)...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoomName = "Salle de Rédaction Finale";
    const finaliseurName = "Le Finaliseur";
    const dossierName = "Dossier de Rédaction Final";

    // 1. Create a Code node that merges ALL items into ONE and makes it human-readable
    const dossierNode = {
      parameters: {
        jsCode: `
const allItems = $input.all();
const merged = {};
allItems.forEach(item => Object.assign(merged, item.json));

// We create a clean string for the AI
return {
    nom_produit: merged.name || "Produit Kilatech",
    briefing: \`
PRODUIT : \${merged.name}
---
TEXTE DU RÉDACTEUR :
\${merged.text || "Non fourni"}

CONSIGNES DU DESIGNER (ALT TAGS) :
\${merged.seo_alt || "Non fourni"}

CRITIQUES DU RÉDACTEUR EN CHEF :
\${merged.text_chef || "Aucune critique"}

OPTIMISATIONS DE L'ANALYSTE SEO :
\${merged.text_analyste || "Aucune optimisation"}

POSTS SOCIAUX (CM) :
\${merged.text_cm || "Non fourni"}
\`
};
        `
      },
      name: dossierName,
      type: "n8n-nodes-base.code",
      typeVersion: 2,
      position: [1950, 256]
    };
    wf.nodes.push(dossierNode);

    // 2. Rewire: Room -> Dossier -> Finaliseur
    // Find connection from Room to Finaliseur and replace it
    wf.connections[finalRoomName].main[0] = [
        {
            node: dossierName,
            type: "main",
            index: 0
        }
    ];

    wf.connections[dossierName] = {
        main: [
            [
                {
                    node: finaliseurName,
                    type: "main",
                    index: 0
                }
            ]
        ]
    };

    // 3. Update Finaliseur Prompt to be very clear
    const finaliseur = wf.nodes.find(n => n.name === finaliseurName);
    if (finaliseur) {
        // We use the human-readable briefing
        const promptText = "Tu es le Directeur de Publication de Kilatech.ma. Voici ton briefing complet pour le produit {{ $json.nom_produit }} :\n\n{{ $json.briefing }}\n\nMISSION :\nProduis maintenant le code HTML final parfait (Title, Meta, H1, Corps de texte) en respectant TOUTES les consignes ci-dessus.\n\nRenvoie UNIQUEMENT le code HTML.";
        
        if (finaliseur.type.includes('langchain')) {
             finaliseur.parameters.responses.values[0].content = "=" + promptText;
        } else {
             finaliseur.parameters.prompt = "=" + promptText;
        }
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("The 'Dossier de Rédaction' is now active. AI will now work perfectly!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
