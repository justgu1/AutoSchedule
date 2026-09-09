import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import CloseIcon from '@mui/icons-material/Close';
import Box from '@mui/material/Box';
import Dialog from '@mui/material/Dialog';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import { type KeyboardEvent, useState } from 'react';
import type { VehicleImage } from '../lib/vehicles';

interface ImageLightboxProps {
    images: VehicleImage[];
    initialIndex: number;
    open: boolean;
    onClose: () => void;
}

/** `Dialog` já prende o foco e fecha com Escape -- as setas do teclado aqui só trocam a imagem atual. */
export function ImageLightbox({ images, initialIndex, open, onClose }: ImageLightboxProps) {
    const [index, setIndex] = useState(initialIndex);

    if (images.length === 0) {
        return null;
    }

    const current = images[Math.min(index, images.length - 1)];

    function go(delta: number) {
        setIndex((current) => (current + delta + images.length) % images.length);
    }

    function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
        if (event.key === 'ArrowRight') {
            go(1);
        } else if (event.key === 'ArrowLeft') {
            go(-1);
        }
    }

    return (
        <Dialog
            open={open}
            onClose={onClose}
            fullScreen
            onTransitionEnter={() => setIndex(initialIndex)}
            slotProps={{ paper: { onKeyDown: handleKeyDown, sx: { bgcolor: 'common.black' } } }}
        >
            <IconButton
                aria-label="Fechar"
                onClick={onClose}
                sx={{ position: 'absolute', top: 8, right: 8, color: 'common.white', zIndex: 1 }}
            >
                <CloseIcon />
            </IconButton>

            <Stack sx={{ height: '100%', justifyContent: 'center', alignItems: 'center', position: 'relative' }}>
                {images.length > 1 && (
                    <IconButton
                        aria-label="Imagem anterior"
                        onClick={() => go(-1)}
                        sx={{ position: 'absolute', left: 8, color: 'common.white' }}
                    >
                        <ArrowBackIcon />
                    </IconButton>
                )}

                <Box
                    component="img"
                    src={current?.url}
                    alt={`Foto ${index + 1} de ${images.length}`}
                    sx={{ maxWidth: '90%', maxHeight: '75%', objectFit: 'contain' }}
                />

                {images.length > 1 && (
                    <IconButton
                        aria-label="Próxima imagem"
                        onClick={() => go(1)}
                        sx={{ position: 'absolute', right: 8, color: 'common.white' }}
                    >
                        <ArrowForwardIcon />
                    </IconButton>
                )}

                {images.length > 1 && (
                    <Stack
                        direction="row"
                        spacing={1}
                        sx={{ position: 'absolute', bottom: 16, overflowX: 'auto', maxWidth: '90%', px: 2 }}
                    >
                        {images.map((image, thumbIndex) => (
                            <Box
                                key={image.id}
                                component="button"
                                type="button"
                                aria-label={`Ir pra foto ${thumbIndex + 1}`}
                                aria-current={thumbIndex === index}
                                onClick={() => setIndex(thumbIndex)}
                                sx={{
                                    p: 0,
                                    border: '2px solid',
                                    borderColor: thumbIndex === index ? 'common.white' : 'transparent',
                                    borderRadius: 1,
                                    cursor: 'pointer',
                                    flexShrink: 0,
                                    lineHeight: 0,
                                }}
                            >
                                <Box
                                    component="img"
                                    src={image.url}
                                    alt=""
                                    sx={{ width: 64, height: 48, objectFit: 'cover' }}
                                />
                            </Box>
                        ))}
                    </Stack>
                )}
            </Stack>
        </Dialog>
    );
}
