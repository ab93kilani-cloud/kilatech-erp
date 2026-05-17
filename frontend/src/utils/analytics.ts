export const initAnalytics = () => {
  // Prevent multiple initializations
  if (window.analyticsInitialized) return;
  window.analyticsInitialized = true;

  console.log("Analytics and Tracking Initialized (Consent Granted)");

  // 1. Google Analytics 4 (GA4)
  const gaId = 'G-LDFN3G23KM'; // ID Kilatech
  const gaScript = document.createElement('script');
  gaScript.async = true;
  gaScript.src = `https://www.googletagmanager.com/gtag/js?id=${gaId}`;
  document.head.appendChild(gaScript);

  window.dataLayer = window.dataLayer || [];
  function gtag(...args: any[]){window.dataLayer.push(args);}
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', gaId);

  // 2. Meta Pixel (Facebook)
  const fbqScript = document.createElement('script');
  fbqScript.innerHTML = `
    !function(f,b,e,v,n,t,s)
    {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    n.queue=[];t=b.createElement(e);t.async=!0;
    t.src=v;s=b.getElementsByTagName(e)[0];
    s.parentNode.insertBefore(t,s)}(window, document,'script',
    'https://connect.facebook.net/en_US/fbevents.js');
    fbq('init', '1711259076714743'); // ID Pixel Kilatech
    fbq('track', 'PageView');
  `;
  document.head.appendChild(fbqScript);
};

// Global typing for window
declare global {
  interface Window {
    analyticsInitialized: boolean;
    dataLayer: any[];
    gtag: (...args: any[]) => void;
    fbq: (...args: any[]) => void;
  }
}
