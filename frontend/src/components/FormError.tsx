import Alert from '@mui/material/Alert';

interface FormErrorProps {
    message?: string | null;
}

/** Erro de nível de request (não amarrado a um campo) -- ausência de mensagem não renderiza nada. */
export function FormError({ message }: FormErrorProps) {
    if (!message) {
        return null;
    }

    return (
        <Alert severity="error" sx={{ mt: 2 }}>
            {message}
        </Alert>
    );
}
