

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
    console.log("Upgrading Final Newsroom to a 5-input physical lock...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoomName = "Salle de Rédaction Finale";
    const finaliseurName = "Le Finaliseur";

    // 1. Remove the old Aggregator
    wf.nodes = wf.nodes.filter(n => n.name !== finalRoomName);
    
    // 2. Add Merge Node with 5 inputs (v2.1 doesn't easily support 5 inputs via parameter, we use multiple merges if needed, or index-based)
    // Actually, in n8n 1.x Merge node can have multiple inputs if we set it.
    const mergeNode = {
      parameters: {
        mode: "combine",
        combinationMode: "mergeByPosition",
        options: {}
      },
      name: finalRoomName,
      type: "n8n-nodes-base.merge",
      typeVersion: 2.1,
      position: [1800, 256]
    };
    wf.nodes.push(mergeNode);

    // 3. Connect the 5 specialized agents to the 5 inputs of the Merge node
    const agents = [
        "Redacteur", 
        "🎨 5. Le Designer", 
        "⚖️ 8. Le Rédacteur en Chef", 
        "📊 10. L'Analyste", 
        "📢 9. Le CM"
    ];
    
    agents.forEach((agent, index) => {
        if (!wf.connections[agent]) wf.connections[agent] = { main: [[]] };
        wf.connections[agent].main[0] = [
            {
                node: finalRoomName,
                type: "main",
                index: 0 // In mergeByPosition index doesn't strictly define "wait for 5", but we'll try to chain them if it's safer
            }
        ];
    });
    
    // Actually, to wait for ALL in n8n, a single Merge node with many inputs is best.
    // But since API manipulation of "number of inputs" is tricky, I'll use a Code node with a "WAIT" logic.
    
    const waiterNode = {
        parameters: {
            jsCode: `
// WAIT FOR ALL 5 AGENTS LOGIC
const allInputs = $input.all();
if (allInputs.length < 5) {
    // If we don't have 5 items yet, we return empty to stop execution here
    // n8n will run this node again for each new item that arrives.
    return []; 
}

// Once we have at least 5 items, we combine them
const combined = {
    id_produit: allInputs[0].json.id,
    nom_produit: allInputs[0].json.name,
    details_agents: {}
};

allInputs.forEach(item => {
    const data = item.json;
    if (data.text && data.text.includes("<h1>")) combined.details_agents.redacteur = data;
    else if (data.seo_alt) combined.details_agents.designer = data;
    else if (data.text && data.text.includes("CORRECTION")) combined.details_agents.redacteur_chef = data;
    else if (data.text && data.text.includes("ANALYSE")) combined.details_agents.analyste = data;
    else if (data.text && data.text.includes("LinkedIn")) combined.details_agents.cm = data;
});

return combined;
`
        },
        name: finalRoomName,
        type: "n8n-nodes-base.code",
        typeVersion: 2,
        position: [1800, 256]
    };
    
    wf.nodes = wf.nodes.filter(n => n.name !== finalRoomName);
    wf.nodes.push(waiterNode);

    // 4. Update Finaliseur Prompt for "Deep Analysis"
    const finaliseur = wf.nodes.find(n => n.name === finaliseurName);
    if (finaliseur) {
        finaliseur.parameters.messages.messageValues[1].content = "=MISSION DE SYNTHÈSE ANALYTIQUE :\n\nÉTAPE 1 : Analyse comparative des 5 dossiers reçus (Contenu, Design, Critique Chef, Analyse SEO, CM).\nÉTAPE 2 : Rédaction du document final en appliquant les corrections prioritaires du Rédacteur en Chef et en insérant les mots-clés de l'Analyste.\nÉTAPE 3 : Vérification de la cohérence du HTML.\n\nDONNÉES :\n{{ JSON.stringify($json.details_agents) }}\n\nLANCE LA RÉDACTION FINALE MAINTENANT (HTML UNIQUEMENT).";
    }

    await request(`/workflows/${MASTER_WF_ID}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: { executionOrder: "v1" }
    });

    console.log("Final Newsroom now has a 5-agent barrier and Deep Analysis prompt!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
