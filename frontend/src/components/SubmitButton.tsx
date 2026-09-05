import Button, { type ButtonProps } from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';

interface SubmitButtonProps extends ButtonProps {
    loading?: boolean;
}

/** Botão de submit com estado de loading embutido -- evita repetir `disabled={isPending}` em cada form. */
export function SubmitButton({ loading = false, disabled, children, ...props }: SubmitButtonProps) {
    return (
        <Button {...props} type="submit" variant="contained" fullWidth disabled={disabled || loading}>
            {loading ? <CircularProgress size={24} color="inherit" /> : children}
        </Button>
    );
}
