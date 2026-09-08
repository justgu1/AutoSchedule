export interface PhotoJob {
    job_id: string;
    status_url: string;
    events_url: string;
}

/** `TResult` é o formato do job terminado -- foto única devolve `{ photo_url }`, lote devolve `{ added }`. */
export interface PhotoJobStatus<TResult = Record<string, unknown>> {
    status: 'queued' | 'processing' | 'done' | 'failed';
    step: string;
    progress: number;
    result?: TResult;
    error?: string;
}

/**
 * SSE -- `onUpdate` roda a cada evento (inclusive o final), já recebendo o status terminal
 * (`done`/`failed`); quem chama decide o que fazer. Fecha a conexão sozinho assim que chega
 * num status terminal. Genérico de propósito: mesmo mecanismo serve upload de concessionária
 * e de veículo, sem depender de nenhum dos dois.
 */
export function subscribeToPhotoJob<TResult = Record<string, unknown>>(
    eventsUrl: string,
    onUpdate: (status: PhotoJobStatus<TResult>) => void,
): () => void {
    // `eventsUrl` vem do backend sem o prefixo `/api` -- `EventSource` não passa por `apiClient`, então o prefixo precisa ser somado aqui.
    const source = new EventSource(`/api${eventsUrl}`, { withCredentials: true });

    source.addEventListener('progress', (event) => {
        const status = JSON.parse((event as MessageEvent<string>).data) as PhotoJobStatus<TResult>;
        onUpdate(status);

        if (status.status === 'done' || status.status === 'failed') {
            source.close();
        }
    });

    // Erro de rede/reconexão do EventSource, não um evento "failed" do job em si -- só encerra, quem chama já tem o último status conhecido.
    source.onerror = () => source.close();

    return () => source.close();
}
