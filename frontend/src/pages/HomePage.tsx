import Button from '@mui/material/Button';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router-dom';

export function HomePage() {
    return (
        <Stack spacing={2} sx={{ alignItems: 'flex-start' }}>
            <Typography variant="h4" component="h1">
                Agendamento de visitas a veículos
            </Typography>
            <Typography color="text.secondary">
                Entre na sua conta ou cadastre-se pra ver o histórico dos seus agendamentos.
            </Typography>
            <Stack direction="row" spacing={2}>
                <Button component={RouterLink} to="/login" variant="contained">
                    Entrar
                </Button>
                <Button component={RouterLink} to="/register" variant="outlined">
                    Cadastrar
                </Button>
            </Stack>
        </Stack>
    );
}
