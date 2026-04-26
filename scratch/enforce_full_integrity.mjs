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
    
    console.log("Enforcing total content integrity for Finaliseur and Director...");

    const finaliseur = wf.nodes.find(n => n.name === "Le Finaliseur");
    const director = wf.nodes.find(n => n.name.includes("Directeur"));

    if (finaliseur) {
        finaliseur.parameters.prompt = "=Tu es l'Expert en Synthèse Finale pour Kilatech.ma.\\n\\nVOICI LE RAPPORT D'AUDIT ET LE CONTENU :\\n{{ $json.text }}\\n\\nMISSION CRITIQUE :\\n1. Produis le document HTML FINAL et COMPLET.\\n2. INCLUSION OBLIGATOIRE : Garde le titre principal en <h1>, les sous-titres en <h2> et <h3>, et tous les paragraphes, listes et liens.\\n3. CORRECTIONS : Intègre les remarques de l'analyste dans ce document.\\n4. NETTOYAGE : Supprime tout ce qui n'est pas du code HTML (pas de notes, pas de scores, pas de posts sociaux).\\n\\nRÉPONDS UNIQUEMENT AVEC LE CODE HTML COMPLET (H1, H2, H3, P, UL, LI, A).";
    }

    if (director) {
        // We set the body parameters to take everything from the Finaliseur
        director.parameters.bodyParameters = {
          parameters: [
            {
              name: "seo_title",
              value: "={{ $node[\"Le Finaliseur\"].json.text.match(/<h1>(.*?)<\\/h1>/)[1] || $node[\"Architect\"].json.h1 }}"
            },
            {
              name: "seo_description",
              value: "={{ $node[\"Architect\"].json.meta_description }}"
            },
            {
              name: "seo_keywords",
              value: "={{ $node[\"Architect\"].json.keywords }}"
            },
            {
              name: "seo_content",
              value: "={{ $node[\"Le Finaliseur\"].json.text }}"
            }
          ]
        };
    }

    await request(`/workflows/${masterId}`, 'PUT', {
      name: wf.name,
      nodes: wf.nodes,
      connections: wf.connections,
      settings: {}
    });
    
    console.log("Full Integrity Locked. H1, H2, H3 are preserved in the final injection.");

  } catch (err) {
    console.error(err.message);
  }
}
main();
