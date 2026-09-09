import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Grid from '@mui/material/Grid';
import LinearProgress from '@mui/material/LinearProgress';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useEffect, useRef, useState } from 'react';
import { ResponsiveDialog as Dialog } from './ResponsiveDialog';
import { FormError } from './FormError';
import { ApiError } from '../lib/apiClient';
import { subscribeToPhotoJob, type PhotoJobStatus } from '../lib/jobs';
import {
    addVehiclePhotos,
    getVehicle,
    removeVehiclePhoto,
    reorderVehiclePhotos,
    type Vehicle,
    type VehicleImage,
} from '../lib/vehicles';

const STEP_LABEL: Record<string, string> = {
    queued: 'Na fila...',
    done: 'Concluído.',
    failed: 'Falhou.',
};

/** Passo de processamento vem como `optimizing N/M` (`ProcessVehiclePhotos`), sem chave fixa igual os outros. */
function stepLabel(step: string): string {
    const match = /^optimizing (\d+)\/(\d+)$/.exec(step);

    return match ? `Otimizando ${match[1]}/${match[2]}...` : (STEP_LABEL[step] ?? step);
}

interface VehicleGalleryDialogProps {
    open: boolean;
    vehicle: Vehicle | null;
    onClose: () => void;
}

/**
 * Lote inteiro (até 10 arquivos) sob um `job_id` só, acompanhado ao vivo via SSE. Reordenar é
 * local até "Salvar ordem": a lista inteira vai pro backend de uma vez, porque ordem parcial
 * não dá pra validar.
 */
export function VehicleGalleryDialog({ open, vehicle, onClose }: VehicleGalleryDialogProps) {
    const queryClient = useQueryClient();
    const fileInputRef = useRef<HTMLInputElement>(null);
    const unsubscribeRef = useRef<(() => void) | null>(null);
    const [job, setJob] = useState<PhotoJobStatus<{ added: number }> | null>(null);
    const [order, setOrder] = useState<VehicleImage[]>([]);
    // Sincroniza durante o render, não num efeito: `order` é derivado da query mas continua
    // localmente mutável até "Salvar ordem" -- upload/remoção invalidam a query, e é isso que precisa refletir aqui.
    const [syncedImages, setSyncedImages] = useState<VehicleImage[] | undefined>(undefined);

    const detail = useQuery({
        queryKey: ['vehicle', vehicle?.id],
        queryFn: () => getVehicle(vehicle!.id),
        enabled: open && vehicle !== null,
    });

    if (detail.data && detail.data.images !== syncedImages) {
        setSyncedImages(detail.data.images);
        setOrder(detail.data.images);
    }

    useEffect(() => {
        return () => unsubscribeRef.current?.();
    }, []);

    function handleClose() {
        setJob(null);
        unsubscribeRef.current?.();
        onClose();
    }

    function invalidate() {
        void queryClient.invalidateQueries({ queryKey: ['vehicle', vehicle?.id] });
        void queryClient.invalidateQueries({ queryKey: ['vehicles'] });
    }

    const uploadMutation = useMutation({
        mutationFn: (files: File[]) => addVehiclePhotos(vehicle!.id, files),
        onSuccess: (photoJob) => {
            setJob({ status: 'queued', step: 'queued', progress: 0 });
            unsubscribeRef.current = subscribeToPhotoJob<{ added: number }>(photoJob.events_url, (status) => {
                setJob(status);

                if (status.status === 'done') {
                    invalidate();
                }
            });
        },
    });
    const removeMutation = useMutation({
        mutationFn: (imageId: string) => removeVehiclePhoto(vehicle!.id, imageId),
        onSuccess: invalidate,
    });
    const reorderMutation = useMutation({
        mutationFn: () =>
            reorderVehiclePhotos(
                vehicle!.id,
                order.map((image) => image.id),
            ),
        onSuccess: invalidate,
    });

    function handleFilesSelected(event: React.ChangeEvent<HTMLInputElement>) {
        const files = Array.from(event.target.files ?? []);
        event.target.value = '';

        if (files.length > 0) {
            uploadMutation.mutate(files);
        }
    }

    function move(index: number, direction: -1 | 1) {
        setOrder((current) => {
            const next = [...current];
            const target = index + direction;

            if (target < 0 || target >= next.length) {
                return current;
            }

            [next[index], next[target]] = [next[target], next[index]];

            return next;
        });
    }

    const serverOrder = (detail.data?.images ?? []).map((image) => image.id).join(',');
    const orderChanged = order.map((image) => image.id).join(',') !== serverOrder;
    const error = uploadMutation.error instanceof ApiError ? uploadMutation.error : null;
    const busy = job !== null && job.status !== 'done' && job.status !== 'failed';

    return (
        <Dialog open={open} onClose={busy ? undefined : handleClose} maxWidth="sm" fullWidth>
            <DialogTitle>
                Galeria -- {vehicle?.brand} {vehicle?.model}
            </DialogTitle>
            <DialogContent>
                {detail.isPending && (
                    <Box sx={{ display: 'flex', justifyContent: 'center', py: 2 }}>
                        <CircularProgress size={24} />
                    </Box>
                )}

                {order.length === 0 && !detail.isPending && (
                    <Typography color="text.secondary" sx={{ mb: 2 }}>
                        Nenhuma foto ainda.
                    </Typography>
                )}

                <Grid container spacing={1} sx={{ mb: 2 }}>
                    {order.map((image, index) => (
                        <Grid key={image.id} size={4}>
                            <Paper variant="outlined" sx={{ p: 0.5 }}>
                                <Box
                                    component="img"
                                    src={image.url}
                                    alt=""
                                    sx={{ width: '100%', height: 100, objectFit: 'cover', borderRadius: 0.5 }}
                                />
                                <Box sx={{ display: 'flex', justifyContent: 'space-between', mt: 0.5 }}>
                                    <Box>
                                        <Button size="small" disabled={index === 0} onClick={() => move(index, -1)}>
                                            Subir
                                        </Button>
                                        <Button
                                            size="small"
                                            disabled={index === order.length - 1}
                                            onClick={() => move(index, 1)}
                                        >
                                            Descer
                                        </Button>
                                    </Box>
                                    <Button
                                        size="small"
                                        color="error"
                                        disabled={removeMutation.isPending}
                                        onClick={() => removeMutation.mutate(image.id)}
                                    >
                                        Remover
                                    </Button>
                                </Box>
                            </Paper>
                        </Grid>
                    ))}
                </Grid>

                {job && (
                    <Box sx={{ mb: 2 }}>
                        <LinearProgress variant="determinate" value={job.progress} />
                        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
                            {stepLabel(job.step)}
                        </Typography>
                    </Box>
                )}

                {job?.status === 'failed' && <FormError message={job.error ?? 'Falha ao processar o lote.'} />}
                {reorderMutation.isSuccess && <Alert severity="success">Ordem salva.</Alert>}
                <FormError message={error?.message} />
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    hidden
                    onChange={handleFilesSelected}
                />
            </DialogContent>
            <DialogActions sx={{ px: 3, pb: 2 }}>
                {orderChanged && (
                    <Button disabled={reorderMutation.isPending} onClick={() => reorderMutation.mutate()}>
                        Salvar ordem
                    </Button>
                )}
                <Button
                    variant="outlined"
                    disabled={busy || order.length >= 20}
                    startIcon={busy ? <CircularProgress size={16} /> : undefined}
                    onClick={() => fileInputRef.current?.click()}
                >
                    Adicionar fotos
                </Button>
                <Button onClick={handleClose} disabled={busy}>
                    Fechar
                </Button>
            </DialogActions>
        </Dialog>
    );
}
