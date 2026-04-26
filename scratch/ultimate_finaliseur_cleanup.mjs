

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
    console.log("Ultimate cleaning of Le Finaliseur connections...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finaliseurName = "Le Finaliseur";
    const finalRoomName = "Salle de Rédaction Finale";

    // 1. Remove ALL connections pointing to Le Finaliseur
    for (const nodeName in wf.connections) {
        if (wf.connections[nodeName].main) {
            wf.connections[nodeName].main[0] = wf.connections[nodeName].main[0].filter(c => c.node !== finaliseurName);
        }
    }

    // 2. Add ONLY the connection from the Final Room
    if (!wf.connections[finalRoomName]) wf.connections[finalRoomName] = { main: [[]] };
    wf.connections[finalRoomName].main[0] = [
        {
            node: finaliseurName,
            type: "main",
            index: 0
        }
    ];

    // 3. Force-configure OpenAI parameters to be absolutely correct for GPT-4o
    const finaliseur = wf.nodes.find(n => n.name === finaliseurName);
    if (finaliseur) {
        finaliseur.parameters = {
            resource: "chat",
            operation: "message",
            model: "gpt-4o",
            messages: {
                messageValues: [
                    {
                        role: "system",
                        content: "Tu es le Finaliseur Senior de Kilatech.ma. Ta mission est de compiler tout le travail de l'équipe SEO en un seul produit final d'excellence."
                    },
                    {
                        role: "user",
                        content: "=VOICI TOUTES LES DONNÉES RÉUNIES PAR LA SALLE DE RÉDACTION :\n\n{{ JSON.stringify($json) }}\n\nMISSION :\nProduis le code HTML final complet (Titre, Meta, H1, Contenu) en respectant les conseils de l'Analyste et les corrections du Rédacteur en Chef."
                    }
                ]
            },
            options: {
                maxTokens: 4000,
                temperature: 0.5
            }
        };
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("Le Finaliseur is now cleaned, isolated and ready for the final touch!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
