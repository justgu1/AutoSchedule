import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';

interface ConfirmDialogProps {
    open: boolean;
    title: string;
    description: string;
    confirmLabel?: string;
    confirmColor?: 'primary' | 'error';
    loading?: boolean;
    onConfirm: () => void;
    onCancel: () => void;
}

/** Confirmação de ação destrutiva -- dialog do próprio app, não `window.confirm` nativo do browser. */
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel = 'Confirmar',
    confirmColor = 'primary',
    loading = false,
    onConfirm,
    onCancel,
}: ConfirmDialogProps) {
    return (
        <Dialog open={open} onClose={onCancel}>
            <DialogTitle>{title}</DialogTitle>
            <DialogContent>
                <DialogContentText>{description}</DialogContentText>
            </DialogContent>
            <DialogActions>
                <Button onClick={onCancel} disabled={loading}>
                    Cancelar
                </Button>
                <Button onClick={onConfirm} color={confirmColor} disabled={loading} autoFocus>
                    {confirmLabel}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
