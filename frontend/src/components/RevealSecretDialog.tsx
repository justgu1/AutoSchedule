import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import { useState } from 'react';

interface RevealSecretDialogProps {
    open: boolean;
    clientId: string;
    clientSecret: string;
    onClose: () => void;
}

/**
 * Mostra `client_id`/`client_secret` uma única vez -- o backend nunca mais devolve o secret em
 * texto puro depois desta resposta. `Dialog` já prende o foco e fecha com Escape.
 */
export function RevealSecretDialog({ open, clientId, clientSecret, onClose }: RevealSecretDialogProps) {
    const [copiedField, setCopiedField] = useState<'client_id' | 'client_secret' | null>(null);

    function copy(field: 'client_id' | 'client_secret', value: string) {
        void navigator.clipboard
            .writeText(value)
            .then(() => setCopiedField(field))
            .catch(() => undefined);
    }

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>Credencial gerada</DialogTitle>
            <DialogContent>
                <Alert severity="warning" sx={{ mb: 2 }}>
                    Copie o secret agora -- ele não será mostrado de novo.
                </Alert>
                <Stack spacing={2}>
                    <TextField
                        label="Client ID"
                        value={clientId}
                        fullWidth
                        slotProps={{
                            input: {
                                readOnly: true,
                                endAdornment: (
                                    <InputAdornment position="end">
                                        <IconButton
                                            aria-label="Copiar client ID"
                                            onClick={() => copy('client_id', clientId)}
                                        >
                                            <ContentCopyIcon fontSize="small" />
                                        </IconButton>
                                    </InputAdornment>
                                ),
                            },
                        }}
                        helperText={copiedField === 'client_id' ? 'Copiado!' : ' '}
                    />
                    <TextField
                        label="Client Secret"
                        value={clientSecret}
                        fullWidth
                        slotProps={{
                            input: {
                                readOnly: true,
                                endAdornment: (
                                    <InputAdornment position="end">
                                        <IconButton
                                            aria-label="Copiar client secret"
                                            onClick={() => copy('client_secret', clientSecret)}
                                        >
                                            <ContentCopyIcon fontSize="small" />
                                        </IconButton>
                                    </InputAdornment>
                                ),
                            },
                        }}
                        helperText={copiedField === 'client_secret' ? 'Copiado!' : ' '}
                    />
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={onClose} variant="contained">
                    Fechar
                </Button>
            </DialogActions>
        </Dialog>
    );
}
