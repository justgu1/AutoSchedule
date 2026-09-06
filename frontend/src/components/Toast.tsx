import Alert, { type AlertColor } from '@mui/material/Alert';
import Snackbar from '@mui/material/Snackbar';

interface ToastProps {
    open: boolean;
    message: string;
    severity?: AlertColor;
    onClose: () => void;
}

/** Notificação efêmera -- sempre topo-direita, mesma posição em todo o app. */
export function Toast({ open, message, severity = 'success', onClose }: ToastProps) {
    return (
        <Snackbar
            open={open}
            autoHideDuration={8000}
            onClose={onClose}
            anchorOrigin={{ vertical: 'top', horizontal: 'right' }}
        >
            <Alert severity={severity} onClose={onClose} sx={{ width: '100%' }}>
                {message}
            </Alert>
        </Snackbar>
    );
}
