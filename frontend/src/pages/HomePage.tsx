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
                {/* primary.dark em vez do padrão -- outlined com o azul default do MUI (#1976d2)
                    fica em 4.4:1 contra o fundo cinza claro do layout, abaixo do mínimo AA (4.5:1). */}
                <Button
                    component={RouterLink}
                    to="/register"
                    variant="outlined"
                    sx={{ color: 'primary.dark', borderColor: 'primary.dark' }}
                >
                    Cadastrar
                </Button>
            </Stack>
        </Stack>
    );
}
