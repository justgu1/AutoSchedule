import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import LinearProgress from '@mui/material/LinearProgress';
import Typography from '@mui/material/Typography';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { FormError } from './FormError';
import { ApiError } from '../lib/apiClient';
import {
    removeDealershipPhoto,
    setDealershipPhoto,
    subscribeToPhotoJob,
    type Dealership,
    type PhotoJobStatus,
} from '../lib/dealerships';

const STEP_LABEL: Record<string, string> = {
    queued: 'Na fila...',
    optimizing: 'Otimizando a imagem...',
    saving: 'Salvando...',
    done: 'Concluído.',
    failed: 'Falhou.',
};

interface DealershipPhotoDialogProps {
    open: boolean;
    dealership: Dealership | null;
    onClose: () => void;
}

/**
 * Só uma foto por concessionária -- um upload novo substitui a anterior.
 * Processamento roda no worker, acompanhado ao vivo via SSE. `photoUrl`
 * local (não a prop `dealership.photo_url` direto) é o que a tela mostra --
 * o job termina e a lista só é revalidada depois, então a prop fica um
 * passo atrás até o próximo fetch.
 */
export function DealershipPhotoDialog({ open, dealership, onClose }: DealershipPhotoDialogProps) {
    const queryClient = useQueryClient();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const unsubscribeRef = useRef<(() => void) | null>(null);
    const [job, setJob] = useState<PhotoJobStatus | null>(null);
    const [photoUrl, setPhotoUrl] = useState<string | null>(() => dealership?.photo_url ?? null);

    useEffect(() => {
        // Fecha a conexão SSE aberta se o componente desmontar no meio do processamento.
        return () => unsubscribeRef.current?.();
    }, []);

    // Reset é acionado pelo próprio fechamento (evento, não efeito) -- limpa antes de avisar o pai, então reabrir a mesma concessionária já nasce sem o job antigo.
    function handleClose() {
        setJob(null);
        unsubscribeRef.current?.();
        onClose();
    }

    const uploadMutation = useMutation({
        mutationFn: (file: File) => setDealershipPhoto(dealership!.id, file),
        onSuccess: (photoJob) => {
            setJob({ status: 'queued', step: 'queued', progress: 0 });
            unsubscribeRef.current = subscribeToPhotoJob(photoJob.events_url, (status) => {
                setJob(status);

                if (status.status === 'done' && status.result) {
                    setPhotoUrl(status.result.photo_url);
                    void queryClient.invalidateQueries({ queryKey: ['dealerships'] });
                }
            });
        },
    });
    const removeMutation = useMutation({
        mutationFn: () => removeDealershipPhoto(dealership!.id),
        onSuccess: () => {
            setPhotoUrl(null);
            void queryClient.invalidateQueries({ queryKey: ['dealerships'] });
        },
    });

    function handleFileSelected(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (file) {
            uploadMutation.mutate(file);
        }
    }

    const error = uploadMutation.error instanceof ApiError ? uploadMutation.error : null;
    const busy = job !== null && job.status !== 'done' && job.status !== 'failed';

    return (
        <Dialog open={open} onClose={busy ? undefined : handleClose} maxWidth="xs" fullWidth>
            <DialogTitle>Foto -- {dealership?.name}</DialogTitle>
            <DialogContent>
                <Box sx={{ display: 'flex', justifyContent: 'center', mb: 2 }}>
                    {photoUrl ? (
                        <Box
                            component="img"
                            src={photoUrl}
                            alt=""
                            sx={{ maxWidth: '100%', maxHeight: 220, borderRadius: 1 }}
                        />
                    ) : (
                        <Typography color="text.secondary">Nenhuma foto ainda.</Typography>
                    )}
                </Box>

                {job && (
                    <Box sx={{ mb: 2 }}>
                        <LinearProgress variant="determinate" value={job.progress} />
                        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                            {STEP_LABEL[job.step] ?? job.step}
                        </Typography>
                    </Box>
                )}

                {job?.status === 'failed' && <FormError message={job.error ?? 'Falha ao processar a foto.'} />}
                <FormError message={error?.message} />
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                    onChange={handleFileSelected}
                />
            </DialogContent>
            <DialogActions sx={{ px: 3, pb: 2 }}>
                {photoUrl && (
                    <Button
                        color="error"
                        disabled={busy || removeMutation.isPending}
                        onClick={() => removeMutation.mutate()}
                    >
                        Remover
                    </Button>
                )}
                <Button
                    variant="outlined"
                    disabled={busy}
                    startIcon={busy ? <CircularProgress size={16} /> : undefined}
                    onClick={() => fileInputRef.current?.click()}
                >
                    {photoUrl ? 'Trocar foto' : 'Adicionar foto'}
                </Button>
                <Button onClick={handleClose} disabled={busy}>
                    Fechar
                </Button>
            </DialogActions>
        </Dialog>
    );
}
