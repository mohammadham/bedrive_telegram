import {useSettings} from '@ui/settings/use-settings';
import {useEffect, useRef} from 'react';

export function EnamadFooterBadge() {
  const {
    billing: {enamad},
  } = useSettings();
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!enamad?.code || !containerRef.current) return;

    // Clear previous content
    containerRef.current.innerHTML = enamad.code;

    // Execute any scripts in the HTML
    const scripts = containerRef.current.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
      const script = scripts[i];
      const newScript = document.createElement('script');
      
      if (script.src) {
        newScript.src = script.src;
      } else {
        newScript.textContent = script.textContent;
      }
      
      // Copy all attributes
      Array.from(script.attributes).forEach(attr => {
        newScript.setAttribute(attr.name, attr.value);
      });
      
      script.parentNode?.replaceChild(newScript, script);
    }
  }, [enamad?.code]);

  if (!enamad?.enable || !enamad?.code || !enamad?.show_in_footer) {
    return null;
  }

  return (
    <div
      ref={containerRef}
      className="mb-14 flex justify-center items-center md:justify-start"
      style={{minHeight: '80px'}}
    />
  );
}
