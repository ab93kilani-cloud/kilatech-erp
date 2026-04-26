

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
    console.log("Upgrading Le Finaliseur to a multi-agent synthesizer...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finaliseurName = "Le Finaliseur";
    const mergeFinalName = "Salle de Rédaction Finale";

    // 1. Create a Merge node to wait for ALL agents
    const mergeNode = {
      parameters: {
        mode: "combine",
        combinationMode: "mergeByPosition"
      },
      name: mergeFinalName,
      type: "n8n-nodes-base.merge",
      typeVersion: 2.1,
      position: [1800, 256]
    };
    wf.nodes.push(mergeNode);

    // 2. Identify all agents pointing to Finaliseur
    const agents = ["Redacteur", "🎨 5. Le Designer", "🔗 6. Le Tisseur", "⚖️ 8. Le Rédacteur en Chef", "📢 9. Le CM", "📊 10. L'Analyste"];
    
    agents.forEach((agent, index) => {
        if (wf.connections[agent]) {
            wf.connections[agent].main[0] = wf.connections[agent].main[0].map(c => {
                if (c.node === finaliseurName) {
                    return { node: mergeFinalName, type: "main", index: 0 }; // We use multiple inputs if needed, or index 0 for mergeByPosition
                }
                return c;
            });
        }
    });

    // 3. Connect Merge -> Finaliseur
    wf.connections[mergeFinalName] = {
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

    // 4. Upgrade Finaliseur to GPT-4o Chat
    const finaliseur = wf.nodes.find(n => n.name === finaliseurName);
    if (finaliseur) {
        finaliseur.parameters.resource = "chat";
        finaliseur.parameters.operation = "message";
        finaliseur.parameters.model = "gpt-4o";
        finaliseur.parameters.messages = {
            messageValues: [
                {
                    role: "system",
                    content: "Tu es le Finaliseur SEO de Kilatech.ma. Ta mission est de produire le code HTML final d'une page produit en fusionnant le travail de toute l'équipe."
                },
                {
                    role: "user",
                    content: "=VOICI LES CONTRIBUTIONS DE L'ÉQUIPE :\n\n1. CONTENU (Rédacteur) :\n{{ $node[\"Redacteur\"].json.text }}\n\n2. DESIGN (Designer) :\n{{ $node[\"🎨 5. Le Designer\"].json.text }}\n\n3. CONSEILS DU RÉDACTEUR EN CHEF :\n{{ $node[\"⚖️ 8. Le Rédacteur en Chef\"].json.text }}\n\n4. ANALYSE DE PERFORMANCE (Analyste) :\n{{ $node[\"📊 10. L'Analyste\"].json.text }}\n\nMISSION :\nProduis le document HTML COMPLET en appliquant les corrections du Rédacteur en Chef et les optimisations de l'Analyste.\n\nFORMAT OBLIGATOIRE :\n<title>...</title>\n<meta name=\"description\" content=\"...\">\n<h1>...</h1>\n[Corps de l'article en HTML]"
                }
            ]
        };
        delete finaliseur.parameters.prompt;
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });

    console.log("Le Finaliseur is now a high-level synthesizer!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
