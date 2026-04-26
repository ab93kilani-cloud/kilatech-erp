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
    
    console.log("Cleaning HTML envelopes from Agents 6 and 8...");

    const agent6 = wf.nodes.find(n => n.name.includes("Tisseur"));
    const agent8 = wf.nodes.find(n => n.name.includes("Rédacteur en Chef"));

    if (agent6) {
        agent6.parameters.prompt = "=Tu es l'Expert en Maillage Interne pour Kilatech.ma.\\n\\nVOICI LE TEXTE ACTUEL :\\n{{ $json.text }}\\n\\nMISSION :\\n1. Conserve 100% du texte. Ne pas mettre de balises <html>, <head> ou <body>.\\n2. Ajoute tes liens HTML (<a href='...'>) sur des mots-clés médicaux.\\n3. Réponds uniquement avec le contenu HTML (titres, paragraphes, listes).";
    }
    if (agent8) {
        agent8.parameters.prompt = "=Tu es le Rédacteur en Chef de Kilatech.ma.\\n\\nCONTENU :\\n{{ $json.text }}\\n\\nMISSION : Valide la qualité et retire tout code technique inutile (pas de <html>, pas de <body>). Je veux uniquement le texte pur formaté en HTML (h1, h2, p).";
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Agents cleaned. HTML envelope issue resolved.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
