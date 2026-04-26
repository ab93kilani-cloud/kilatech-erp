const TELEGRAM_TOKEN = '8797554466:AAE19aeb9b0dNUyVimG1cAqvXqKgzwsSCUo';

async function main() {
  const res = await fetch(`https://api.telegram.org/bot${TELEGRAM_TOKEN}/getUpdates`);
  const data = await res.json();
  
  if (!data.ok) {
    console.log("Telegram API Error:", data.description);
    return;
  }
  
  if (data.result.length === 0) {
    console.log("AUCUN MESSAGE REÇU. Vous devez d'abord envoyer /start à votre bot dans Telegram !");
    return;
  }
  
  console.log("=== CHAT IDs TROUVÉS ===");
  data.result.forEach(update => {
    const chat = update.message?.chat;
    if (chat) {
      console.log(`Chat ID: ${chat.id} | Nom: ${chat.first_name || chat.title}`);
    }
  });
}
main().catch(console.error);
