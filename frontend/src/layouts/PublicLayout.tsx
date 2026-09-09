import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Container from '@mui/material/Container';
import Stack from '@mui/material/Stack';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { Link as RouterLink, Outlet } from 'react-router-dom';
import { getMe, logout } from '../lib/auth';

/**
 * Casco de página pública -- header com login/painel conforme a sessão, footer fixo. `getMe`
 * com `retry: false`: sem sessão é o resultado esperado (401), não motivo pra tentar de novo.
 */
export function PublicLayout() {
    const queryClient = useQueryClient();
    const me = useQuery({ queryKey: ['me'], queryFn: getMe, retry: false });

    function handleLogout() {
        void logout()
            .catch(() => undefined)
            .finally(() => queryClient.removeQueries({ queryKey: ['me'] }));
    }

    return (
        <Box sx={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', bgcolor: 'grey.50' }}>
            {/* `sticky` -- acompanha a rolagem em vez de sumir, mesmo critério do header autenticado. */}
            <AppBar position="sticky" color="default" elevation={1} sx={{ top: 0 }}>
                <Toolbar sx={{ justifyContent: 'space-between' }}>
                    <Typography
                        variant="h6"
                        component={RouterLink}
                        to="/"
                        sx={{ color: 'text.primary', textDecoration: 'none' }}
                    >
                        AutoSchedule
                    </Typography>
                    {/* `primary.dark` em vez do padrão -- o azul default do MUI (#1976d2) fica em 4.2:1 contra
                        o fundo cinza claro do header, abaixo do mínimo AA (4.5:1). */}
                    <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                        {me.data ? (
                            <>
                                <Button
                                    component={RouterLink}
                                    to={me.data.role === 'customer' ? '/me' : '/vehicles'}
                                    sx={{ color: 'primary.dark' }}
                                >
                                    Entrar no painel
                                </Button>
                                <Button onClick={handleLogout} sx={{ color: 'primary.dark' }}>
                                    Sair
                                </Button>
                            </>
                        ) : (
                            <>
                                <Button component={RouterLink} to="/login" sx={{ color: 'primary.dark' }}>
                                    Entrar
                                </Button>
                                <Button
                                    component={RouterLink}
                                    to="/register"
                                    variant="outlined"
                                    sx={{ color: 'primary.dark', borderColor: 'primary.dark' }}
                                >
                                    Criar conta
                                </Button>
                            </>
                        )}
                    </Stack>
                </Toolbar>
            </AppBar>
            <Container sx={{ py: 4, flex: 1 }}>
                <Outlet />
            </Container>
            <Box
                component="footer"
                sx={{ py: 3, textAlign: 'center', color: 'text.secondary', borderTop: 1, borderColor: 'divider' }}
            >
                <Typography variant="body2">AutoSchedule -- agendamento de visitas a veículos</Typography>
            </Box>
        </Box>
    );
}
