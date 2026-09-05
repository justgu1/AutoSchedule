import TextField, { type TextFieldProps } from '@mui/material/TextField';

interface FormTextFieldProps extends Omit<TextFieldProps, 'error'> {
    /** Mensagem de erro desse campo específico, vinda de `ApiError.errors` -- ausência = campo válido. */
    error?: string;
}

/** TextField do MUI já ligado ao formato de erro por campo que a API devolve. */
export function FormTextField({ error, helperText, ...props }: FormTextFieldProps) {
    return <TextField {...props} fullWidth margin="normal" error={Boolean(error)} helperText={error ?? helperText} />;
}
