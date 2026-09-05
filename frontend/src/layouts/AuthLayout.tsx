import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Paper from '@mui/material/Paper';
import Typography from '@mui/material/Typography';
import { Outlet } from 'react-router-dom';

/** Casco visual só das telas de login/registro/esqueci-senha/redefinir-senha -- card centralizado, sem navegação nenhuma. */
export function AuthLayout() {
    return (
        <Box
            sx={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                bgcolor: 'grey.100',
            }}
        >
            <Container maxWidth="xs">
                <Paper elevation={3} sx={{ p: 4 }}>
                    <Typography variant="h5" component="h1" gutterBottom sx={{ textAlign: 'center' }}>
                        AutoSchedule
                    </Typography>
                    <Outlet />
                </Paper>
            </Container>
        </Box>
    );
}
