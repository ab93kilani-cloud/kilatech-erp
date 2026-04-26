import fs from 'fs';

const N8N_URL = 'http://localhost:5678/api/v1';
const API_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJjZDBlYmZhYy1lNWViLTQ5N2YtYjI5Zi0yMzdkZTk1MjI1MzQiLCJpc3MiOiJuOG4iLCJhdWQiOiJwdWJsaWMtYXBpIiwianRpIjoiNzcyYjIwZjMtMGQ2Mi00YWM4LWIxMmMtMTYzM2VhMzE1MWNlIiwiaWF0IjoxNzc2ODUxNjE2LCJleHAiOjE3ODQ2MDY0MDB9.BJoPXkyV-2bXNX7VsVjOTEfxuZrrRG_iF6SbNVFtLMQ';

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
    const masterId = "Rbx87xuCY2i1VVBQ";
    let wf = await request(`/workflows/${masterId}`, 'GET');
    
    console.log("Boosting Local SEO for Morocco (Architect & Redacteur)...");

    const architect = wf.nodes.find(n => n.name === "Architect");
    const redacteur = wf.nodes.find(n => n.name === "Redacteur");

    if (architect) {
        // We append the local SEO instruction
        architect.parameters.prompt = architect.parameters.prompt + "\\n\\nCONSIGNE SEO LOCAL MAROC : Identifie 4 villes clés (ex: Casablanca, Rabat, Marrakech, Tanger) et inclus les dans ta stratégie.";
    }

    if (redacteur) {
        redacteur.parameters.prompt = redacteur.parameters.prompt + "\\n\\nIMPLANTATION LOCALE : Tu DOIS intégrer une phrase mentionnant la livraison ou l'assistance technique dans les villes suggérées par l'architecte (ex: 'Livraison rapide à Casablanca, Rabat et partout au Maroc') pour rassurer les cliniques et hôpitaux.";
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Local SEO Boost applied. Score 100 in sight!");

  } catch (err) {
    console.error(err.message);
  }
}
main();
