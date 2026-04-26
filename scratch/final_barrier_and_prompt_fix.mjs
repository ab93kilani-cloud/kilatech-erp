

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
    console.log("Applying the 5-agent barrier and final prompt...");
    const wf = await request(`/workflows/${MASTER_WF_ID}`, 'GET');

    const finalRoom = wf.nodes.find(n => n.name === "Salle de Rédaction Finale");
    if (finalRoom) {
        finalRoom.parameters.jsCode = `
// BARRIÈRE DE SÉCURITÉ : ATTENTE DES 5 AGENTS
const allInputs = $input.all();

// On compte les agents qui ont réellement envoyé des données
if (allInputs.length < 5) {
    return []; // On arrête l'exécution ici tant qu'on n'a pas 5 agents
}

const namedReport = {
    id_produit: allInputs[0].json.id || "N/A",
    nom_produit: allInputs[0].json.name || "N/A",
    details_agents: {}
};

allInputs.forEach(item => {
    const data = item.json;
    if (data.text && data.text.includes("<h1>")) namedReport.details_agents.redacteur = data;
    else if (data.seo_alt) namedReport.details_agents.designer = data;
    else if (data.text && data.text.includes("LinkedIn")) namedReport.details_agents.cm = data;
    else if (data.text && data.text.includes("Lien")) namedReport.details_agents.tisseur = data;
    else if (data.text && data.text.includes("ANALYSE")) namedReport.details_agents.analyste = data;
    else if (data.text && data.text.includes("CORRECTION")) namedReport.details_agents.redacteur_chef = data;
});

return namedReport;
        `;
    }

    const finaliseur = wf.nodes.find(n => n.name === "Le Finaliseur");
    if (finaliseur) {
        // We use the correct prompt field for LangChain or standard node
        const promptText = "MISSION DE SYNTHÈSE ANALYTIQUE :\n\n1. Analyse comparative des 5 dossiers (Contenu, Design, Critique Chef, Analyse SEO, CM).\n2. Rédaction du document final HTML en appliquant les corrections et mots-clés.\n\nDONNÉES REÇUES :\n{{ JSON.stringify($json.details_agents) }}\n\nRÉDIGE LE HTML FINAL MAINTENANT.";
        
        if (finaliseur.type.includes('langchain')) {
             finaliseur.parameters.prompt = "=" + promptText;
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

    console.log("Barrier and Prompt successfully applied!");

  } catch (err) {
    console.error(err.message);
  }
}

main();
