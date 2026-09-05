import AppBar from '@mui/material/AppBar';
import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import Toolbar from '@mui/material/Toolbar';
import Typography from '@mui/material/Typography';
import { Outlet } from 'react-router-dom';

/** Casco de página pública (ex: landing) -- header simples, sem nada que dependa de sessão. */
export function PublicLayout() {
    return (
        <Box sx={{ minHeight: '100vh', bgcolor: 'grey.50' }}>
            <AppBar position="static" color="default" elevation={1}>
                <Toolbar>
                    <Typography variant="h6" component="span">
                        AutoSchedule
                    </Typography>
                </Toolbar>
            </AppBar>
            <Container sx={{ py: 4 }}>
                <Outlet />
            </Container>
        </Box>
    );
}
