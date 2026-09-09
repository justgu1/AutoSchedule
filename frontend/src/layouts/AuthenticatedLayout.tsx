import MenuIcon from '@mui/icons-material/Menu';
import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import CircularProgress from '@mui/material/CircularProgress';
import Container from '@mui/material/Container';
import Divider from '@mui/material/Divider';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useMediaQuery, useTheme } from '@mui/material';
import { useState } from 'react';
import { Link as RouterLink, Navigate, Outlet, useNavigate } from 'react-router-dom';
import { getMe, logout } from '../lib/auth';

interface NavLink {
    label: string;
    to: string;
}

/**
 * Casco da área logada -- também é o "auth gate": `GET /me` prova a sessão
 * (cookie `HttpOnly`, não tem token nenhum pra checar no lado do cliente).
 * Sem sessão válida, redireciona pro login em vez de deixar a página quebrar.
 */
export function AuthenticatedLayout() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const theme = useTheme();
    const isDesktop = useMediaQuery(theme.breakpoints.up('md'));
    const [drawerOpen, setDrawerOpen] = useState(false);
    const me = useQuery({ queryKey: ['me'], queryFn: getMe, retry: false });

    if (me.isPending) {
        return (
            <Box sx={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                <CircularProgress aria-label="Carregando" />
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

    const managementLinks: NavLink[] =
        me.data.role === 'admin' || me.data.role === 'seller'
            ? [
                  { label: 'Concessionárias', to: '/dealerships' },
                  { label: 'Veículos', to: '/vehicles' },
                  { label: 'Agendamentos', to: '/appointments' },
              ]
            : [];
    const links: NavLink[] = [{ label: 'Meu perfil', to: '/me' }, ...managementLinks];

    return (
        <Box sx={{ minHeight: '100vh', bgcolor: 'grey.50' }}>
            {/* `sticky` -- header acompanha a rolagem em vez de sumir, mesmo critério do botão de filtros. */}
            <AppBar position="sticky" color="default" elevation={1} sx={{ top: 0 }}>
                <Toolbar sx={{ justifyContent: 'space-between' }}>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: 3, minWidth: 0 }}>
                        <Typography
                            variant="h6"
                            component={RouterLink}
                            to="/"
                            noWrap
                            sx={{ color: 'text.primary', textDecoration: 'none' }}
                        >
                            AutoSchedule
                        </Typography>
                        {isDesktop &&
                            links.map((link) => (
                                <Button
                                    key={link.to}
                                    component={RouterLink}
                                    to={link.to}
                                    size="small"
                                    sx={{ color: 'primary.dark' }}
                                >
                                    {link.label}
                                </Button>
                            ))}
                    </Box>
                    <Box sx={{ display: 'flex', alignItems: 'center', gap: { xs: 1, md: 2 } }}>
                        {isDesktop && <Typography variant="body2">{me.data.name}</Typography>}
                        {isDesktop && (
                            // `primary.dark` em vez do padrão -- o azul default do MUI (#1976d2) fica em 4.2:1 contra
                            // o cinza do AppBar, abaixo do mínimo 4.5:1 de WCAG 2.1 AA (mesmo ajuste de PublicLayout).
                            <Button size="small" onClick={handleLogout} sx={{ color: 'primary.dark' }}>
                                Sair
                            </Button>
                        )}
                        {!isDesktop && (
                            <IconButton
                                aria-label="Abrir menu"
                                onClick={() => setDrawerOpen(true)}
                                sx={{ color: 'primary.dark' }}
                            >
                                <MenuIcon />
                            </IconButton>
                        )}
                    </Box>
                </Toolbar>
            </AppBar>

            {/* Menu do mobile só existe fora do fluxo do AppBar (drawer) -- é o que elimina o overflow
                horizontal que a mesma lista de links, toda inline, causava em telas estreitas. */}
            <Drawer anchor="right" open={drawerOpen} onClose={() => setDrawerOpen(false)}>
                <Box sx={{ width: 260 }} role="presentation">
                    <Typography variant="subtitle2" color="text.secondary" sx={{ px: 2, pt: 2 }}>
                        {me.data.name}
                    </Typography>
                    <List>
                        {links.map((link) => (
                            <ListItemButton
                                key={link.to}
                                component={RouterLink}
                                to={link.to}
                                onClick={() => setDrawerOpen(false)}
                            >
                                <ListItemText primary={link.label} />
                            </ListItemButton>
                        ))}
                    </List>
                    <Divider />
                    <List>
                        <ListItemButton
                            onClick={() => {
                                setDrawerOpen(false);
                                handleLogout();
                            }}
                        >
                            <ListItemText primary="Sair" />
                        </ListItemButton>
                    </List>
                </Box>
            </Drawer>

            <Container sx={{ py: 4 }}>
                <Outlet />
            </Container>
        </Box>
    );
}
