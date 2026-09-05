import { useEffect, useRef } from 'react';

const GOOGLE_CLIENT_ID = import.meta.env.VITE_GOOGLE_CLIENT_ID;
const GIS_SCRIPT_SRC = 'https://accounts.google.com/gsi/client';

declare global {
    interface Window {
        google?: {
            accounts: {
                id: {
                    initialize(config: {
                        client_id: string;
                        callback: (response: { credential: string }) => void;
                    }): void;
                    renderButton(parent: HTMLElement, options: Record<string, unknown>): void;
                };
            };
        };
    }
}

interface GoogleSignInButtonProps {
    onCredential: (idToken: string) => void;
}

/**
 * Botão pronto do Google Identity Services -- entrega um id_token assinado
 * pelo Google via `onCredential`, o backend verifica a assinatura (nunca
 * confiamos nele aqui). Sem `VITE_GOOGLE_CLIENT_ID` configurado, não
 * renderiza nada -- login social é opcional, não quebra o resto da tela.
 */
export function GoogleSignInButton({ onCredential }: GoogleSignInButtonProps) {
    const containerRef = useRef<HTMLDivElement>(null);
    const onCredentialRef = useRef(onCredential);

    // Mantém a ref sincronizada fora do render (regra do react-hooks: nunca mutar ref durante render).
    useEffect(() => {
        onCredentialRef.current = onCredential;
    });

    useEffect(() => {
        if (!GOOGLE_CLIENT_ID) {
            return;
        }

        function render() {
            if (!containerRef.current || !window.google) {
                return;
            }

            window.google.accounts.id.initialize({
                client_id: GOOGLE_CLIENT_ID!,
                callback: (response) => onCredentialRef.current(response.credential),
            });
            window.google.accounts.id.renderButton(containerRef.current, {
                theme: 'outline',
                size: 'large',
                width: 320,
            });
        }

        if (window.google) {
            render();

            return;
        }

        const existingScript = document.querySelector<HTMLScriptElement>(`script[src="${GIS_SCRIPT_SRC}"]`);

        if (existingScript) {
            existingScript.addEventListener('load', render);

            return;
        }

        const script = document.createElement('script');
        script.src = GIS_SCRIPT_SRC;
        script.async = true;
        script.defer = true;
        script.onload = render;
        document.head.appendChild(script);
    }, []);

    if (!GOOGLE_CLIENT_ID) {
        return null;
    }

    return <div ref={containerRef} />;
}
