import Dialog, { type DialogProps } from '@mui/material/Dialog';
import Drawer from '@mui/material/Drawer';
import { useMediaQuery, useTheme } from '@mui/material';

/**
 * Modal centralizado no desktop; bottom sheet (Drawer) no mobile -- um `Dialog` centralizado é
 * difícil de operar em tela pequena (teclado virtual cobre metade, scroll dentro de scroll).
 * Mesma API do `Dialog` (`open`/`onClose`/`maxWidth`/`fullWidth`/children) -- troca só o import.
 */
export function ResponsiveDialog({ open, onClose, maxWidth, fullWidth, fullScreen, children, ...rest }: DialogProps) {
    const theme = useTheme();
    const isDesktop = useMediaQuery(theme.breakpoints.up('md'));

    if (isDesktop || fullScreen) {
        return (
            <Dialog
                open={open}
                onClose={onClose}
                maxWidth={maxWidth}
                fullWidth={fullWidth}
                fullScreen={fullScreen}
                {...rest}
            >
                {children}
            </Dialog>
        );
    }

    return (
        <Drawer
            anchor="bottom"
            open={open}
            onClose={onClose}
            slotProps={{
                paper: {
                    // `Drawer` não vem com papel de dialog pronto (ao contrário do `Dialog`) -- sem isso,
                    // tanto leitor de tela quanto `getByRole('dialog')` do E2E não reconheceriam o modal.
                    role: 'dialog',
                    'aria-modal': true,
                    sx: {
                        maxHeight: '92vh',
                        borderTopLeftRadius: 16,
                        borderTopRightRadius: 16,
                        display: 'flex',
                        flexDirection: 'column',
                    },
                },
            }}
        >
            {children}
        </Drawer>
    );
}
