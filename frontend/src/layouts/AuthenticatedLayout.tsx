import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Container from '@mui/material/Container';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Navigate, Outlet, useNavigate } from 'react-router-dom';
import { getMe, logout } from '../lib/auth';

/**
 * Casco da área logada -- também é o "auth gate": `GET /me` prova a sessão
 * (cookie `HttpOnly`, não tem token nenhum pra checar no lado do cliente).
 * Sem sessão válida, redireciona pro login em vez de deixar a página quebrar.
 */
export function AuthenticatedLayout() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const me = useQuery({ queryKey: ['me'], queryFn: getMe, retry: false });

    if (me.isPending) {
        return (
            <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <CircularProgress />
            </Box>
        );
    }

    if (me.isError) {
        return <Navigate to="/login" replace />;
    }

    function handleLogout() {
        // Mesmo se a requisição falhar (ex: rede), ainda limpa o estado local -- não trava o usuário logado visualmente.
        void logout()
            .catch(() => undefined)
            .finally(() => {
                queryClient.removeQueries({ queryKey: ['me'] });
                void navigate('/login', { replace: true });
            });
    }

    return (
        <Box sx={{ minHeight: '100vh', bgcolor: 'grey.50' }}>
            <AppBar position="static" color="default" elevation={1}>
                <Toolbar sx={{ justifyContent: 'space-between' }}>
                    <Typography variant="h6" component="span">
                        AutoSchedule
                    </Typography>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 2 }}>
                        <Typography variant="body2">{me.data.name}</Typography>
                        <Button size="small" onClick={handleLogout}>
                            Sair
                        </Button>
                    </Box>
                </Toolbar>
            </AppBar>
            <Container sx={{ py: 4 }}>
                <Outlet />
            </Container>
        </Box>
    );
}
