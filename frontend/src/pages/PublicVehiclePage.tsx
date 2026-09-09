import CalendarTodayIcon from '@mui/icons-material/CalendarToday';
import CheckCircleIcon from '@mui/icons-material/CheckCircle';
import PlaceIcon from '@mui/icons-material/Place';
import Alert from '@mui/material/Alert';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import Divider from '@mui/material/Divider';
import Grid from '@mui/material/Grid';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import { alpha } from '@mui/material/styles';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useMutation, useQuery } from '@tanstack/react-query';
import { useState } from 'react';
import { Link as RouterLink, useParams } from 'react-router-dom';
import { Breadcrumb } from '../components/Breadcrumb';
import { DateTimeCarousel } from '../components/DateTimeCarousel';
import { DealershipMap } from '../components/DealershipMap';
import { ImageLightbox } from '../components/ImageLightbox';
import { ApiError } from '../lib/apiClient';
import { createAppointment, type Appointment } from '../lib/appointments';
import { getPublicDealership } from '../lib/dealerships';
import { getPublicVehicle } from '../lib/vehicles';

const TRANSMISSION_LABEL: Record<string, string> = {
    manual: 'Manual',
    automatic: 'Automático',
    automated: 'Automatizado',
    cvt: 'CVT',
};
const BODY_TYPE_LABEL: Record<string, string> = {
    hatch: 'Hatch',
    sedan: 'Sedã',
    suv: 'SUV',
    pickup: 'Picape',
    coupe: 'Cupê',
    convertible: 'Conversível',
    minivan: 'Minivan',
    wagon: 'Perua',
};
const FUEL_TYPE_LABEL: Record<string, string> = {
    flex: 'Flex',
    gasoline: 'Gasolina',
    ethanol: 'Etanol',
    diesel: 'Diesel',
    electric: 'Elétrico',
    hybrid: 'Híbrido',
};

/** Sem conta, sem role -- passo 1 do fluxo de agendamento (`README.md`): visualizar os detalhes do veículo. */
export function PublicVehiclePage() {
    const { id } = useParams<{ id: string }>();
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    const vehicle = useQuery({
        queryKey: ['public-vehicle', id],
        queryFn: () => getPublicVehicle(id!),
        enabled: id !== undefined,
        retry: false,
    });

    // Concessionária aninhada no veículo é enxuta (slug/nome/cidade/UF) -- endereço/telefone/vendedor
    // vêm da mesma rota pública que a página da concessionária já usa, sem duplicar o dado.
    const dealership = useQuery({
        queryKey: ['public-dealership', vehicle.data?.dealership.slug],
        queryFn: () => getPublicDealership(vehicle.data!.dealership.slug),
        enabled: vehicle.data !== undefined,
    });

    if (vehicle.isPending) {
        return (
            <Box sx={{ display: 'flex', justifyContent: 'center', py: 4 }}>
                <CircularProgress aria-label="Carregando" />
            </Box>
        );
    }

    if (vehicle.isError || !vehicle.data) {
        const notFound = vehicle.error instanceof ApiError && vehicle.error.status === 404;

        return (
            <Alert severity="error">
                {notFound ? 'Veículo não encontrado.' : 'Não foi possível carregar o veículo.'}
            </Alert>
        );
    }

    const data = vehicle.data;
    const cover = data.images[0]?.url;
    const year =
        data.manufacture_year && data.model_year && data.manufacture_year !== data.model_year
            ? `${data.manufacture_year}/${data.model_year}`
            : (data.model_year ?? data.manufacture_year ?? '--');

    const specs: { label: string; value: string }[] = [
        { label: 'Cidade', value: `${data.dealership.city}/${data.dealership.state}` },
        { label: 'Ano', value: String(year) },
        { label: 'KM', value: data.mileage_km === null ? '--' : `${data.mileage_km.toLocaleString('pt-BR')} km` },
        {
            label: 'Câmbio',
            value: data.transmission ? (TRANSMISSION_LABEL[data.transmission] ?? data.transmission) : '--',
        },
        { label: 'Carroceria', value: data.body_type ? (BODY_TYPE_LABEL[data.body_type] ?? data.body_type) : '--' },
        { label: 'Combustível', value: data.fuel_type ? (FUEL_TYPE_LABEL[data.fuel_type] ?? data.fuel_type) : '--' },
        { label: 'Final de placa', value: data.plate_end_digit === null ? '--' : String(data.plate_end_digit) },
        { label: 'Cor', value: data.color ?? '--' },
        { label: 'Aceita troca', value: data.accepts_trade ? 'Sim' : 'Não' },
        { label: 'IPVA pago', value: data.ipva_paid ? 'Sim' : 'Não' },
        { label: 'Licenciado', value: data.licensed ? 'Sim' : 'Não' },
    ];

    return (
        <>
            <Breadcrumb
                items={[
                    { label: data.dealership.name, to: `/concessionarias/${data.dealership.slug}` },
                    { label: `${data.brand} ${data.model}` },
                ]}
            />
            <Grid container spacing={3}>
                <Grid size={{ xs: 12, md: 8 }}>
                    <Paper sx={{ overflow: 'hidden', mb: 3 }}>
                        {cover ? (
                            <Box
                                component="button"
                                type="button"
                                onClick={() => setLightboxIndex(0)}
                                sx={{ display: 'block', width: '100%', p: 0, border: 0, cursor: 'pointer' }}
                                aria-label="Ver fotos em tela cheia"
                            >
                                <Box
                                    component="img"
                                    src={cover}
                                    alt=""
                                    sx={{ width: '100%', height: 360, objectFit: 'cover', display: 'block' }}
                                />
                            </Box>
                        ) : (
                            <Box sx={{ height: 360, bgcolor: 'grey.200' }} />
                        )}

                        {data.images.length > 1 && (
                            <Stack direction="row" spacing={1} sx={{ p: 2, overflowX: 'auto' }}>
                                {data.images.map((image, index) => (
                                    <Box
                                        key={image.id}
                                        component="button"
                                        type="button"
                                        onClick={() => setLightboxIndex(index)}
                                        aria-label={`Ver foto ${index + 1} em tela cheia`}
                                        sx={{ p: 0, border: 0, cursor: 'pointer', flexShrink: 0, lineHeight: 0 }}
                                    >
                                        <Box
                                            component="img"
                                            src={image.url}
                                            alt=""
                                            sx={{ width: 96, height: 72, objectFit: 'cover', borderRadius: 1 }}
                                        />
                                    </Box>
                                ))}
                            </Stack>
                        )}
                    </Paper>

                    <Paper sx={{ p: 3 }}>
                        <Stack direction="row" spacing={1} sx={{ alignItems: 'center' }}>
                            <Typography variant="h4" component="h1">
                                {data.brand} {data.model}
                            </Typography>
                            <Chip label="Oferta destaque" color="primary" size="small" />
                        </Stack>
                        <Typography color="text.secondary" sx={{ mb: 2 }}>
                            {data.version}
                        </Typography>

                        <Grid container spacing={2}>
                            {specs.map((spec) => (
                                <Grid key={spec.label} size={{ xs: 6, sm: 4 }}>
                                    <Typography variant="caption" color="text.secondary" component="div">
                                        {spec.label}
                                    </Typography>
                                    <Typography variant="body2">{spec.value}</Typography>
                                </Grid>
                            ))}
                        </Grid>

                        <Divider sx={{ my: 2 }} />

                        <Typography variant="h6" component="h2" gutterBottom>
                            Sobre os diferenciais do anúncio
                        </Typography>
                        <Typography sx={{ mb: 2 }}>{data.description ?? 'Sem descrição adicional.'}</Typography>

                        {data.amenities.length > 0 && (
                            <>
                                <Divider sx={{ my: 2 }} />
                                <Typography variant="h6" component="h2" gutterBottom>
                                    Itens de veículo
                                </Typography>
                                <Box sx={{ columnCount: { xs: 1, sm: 2, md: 3 }, columnGap: 3 }}>
                                    {data.amenities.map((amenity) => (
                                        <Typography key={amenity.id} sx={{ breakInside: 'avoid', mb: 0.5 }}>
                                            {amenity.label}
                                        </Typography>
                                    ))}
                                </Box>
                            </>
                        )}
                    </Paper>

                    {dealership.data && (
                        <Paper sx={{ p: 3, mt: 3 }}>
                            <Typography variant="h6" component="h2" gutterBottom>
                                Vendedor e concessionária
                            </Typography>
                            <Stack direction="row" spacing={2} sx={{ alignItems: 'center', mb: 2, flexWrap: 'wrap' }}>
                                {dealership.data.seller_name && (
                                    <Stack direction="row" spacing={2} sx={{ alignItems: 'center' }}>
                                        <Avatar>{dealership.data.seller_name.charAt(0)}</Avatar>
                                        <Stack>
                                            <Typography variant="subtitle2">Vendedor responsável</Typography>
                                            <Typography color="text.secondary">
                                                {dealership.data.seller_name}
                                            </Typography>
                                        </Stack>
                                    </Stack>
                                )}
                                <Stack sx={{ ml: { sm: 'auto' } }}>
                                    {dealership.data.phone && (
                                        <Typography color="text.secondary">
                                            Telefone: {dealership.data.phone}
                                        </Typography>
                                    )}
                                    {dealership.data.email && (
                                        <Typography color="text.secondary">E-mail: {dealership.data.email}</Typography>
                                    )}
                                </Stack>
                            </Stack>
                            <Typography
                                component={RouterLink}
                                to={`/concessionarias/${data.dealership.slug}`}
                                sx={{ color: 'primary.main', textDecoration: 'none', display: 'block', mb: 2 }}
                            >
                                {data.dealership.name}
                            </Typography>
                            <DealershipMap
                                address={`${dealership.data.address}, ${dealership.data.number} - ${dealership.data.neighborhood}, ${dealership.data.city}/${dealership.data.state}`}
                            />
                        </Paper>
                    )}
                </Grid>

                <Grid size={{ xs: 12, md: 4 }}>
                    <Paper sx={{ p: 3, position: { md: 'sticky' }, top: { md: 16 } }}>
                        <Typography variant="h5" gutterBottom sx={{ mb: 3 }}>
                            {Number(data.price).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })}
                        </Typography>

                        <BookingSection
                            vehicleId={data.id}
                            dealershipAddress={
                                dealership.data
                                    ? `${dealership.data.address}, ${dealership.data.number} - ${dealership.data.city}`
                                    : `${data.dealership.city}/${data.dealership.state}`
                            }
                        />
                    </Paper>
                </Grid>
            </Grid>

            {lightboxIndex !== null && (
                <ImageLightbox
                    images={data.images}
                    initialIndex={lightboxIndex}
                    open
                    onClose={() => setLightboxIndex(null)}
                />
            )}
        </>
    );
}

/** Carrossel de data/horário + formulário de contato, sem modal -- reserva direto na própria página. */
function BookingSection({ vehicleId, dealershipAddress }: { vehicleId: string; dealershipAddress: string }) {
    const [date, setDate] = useState<string | null>(null);
    const [time, setTime] = useState<string | null>(null);
    const [customerName, setCustomerName] = useState('');
    const [customerEmail, setCustomerEmail] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');

    const bookingMutation = useMutation({
        mutationFn: (): Promise<Appointment> =>
            createAppointment({
                vehicle_id: vehicleId,
                scheduled_at: `${date}T${time}:00`,
                customer_name: customerName,
                customer_email: customerEmail,
                customer_phone: customerPhone,
            }),
    });

    const conflictError = bookingMutation.error instanceof ApiError && bookingMutation.error.status === 409;
    const canSubmit =
        date !== null &&
        time !== null &&
        time !== '' &&
        customerName !== '' &&
        customerEmail !== '' &&
        customerPhone !== '';

    if (bookingMutation.isSuccess) {
        const scheduledAt = new Date(bookingMutation.data.scheduled_at).toLocaleString('pt-BR', {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

        return (
            <Stack spacing={3} sx={{ alignItems: 'center', textAlign: 'center', py: 2 }}>
                <Box
                    sx={{
                        display: 'flex',
                        borderRadius: '50%',
                        p: 2,
                        bgcolor: (theme) => alpha(theme.palette.success.main, 0.12),
                    }}
                >
                    <CheckCircleIcon sx={{ fontSize: 56, color: 'success.main' }} />
                </Box>
                <Typography variant="h6" sx={{ fontWeight: 'bold' }}>
                    Agendamento concluído!
                </Typography>
                <Stack
                    direction="row"
                    spacing={2}
                    divider={<Divider orientation="vertical" flexItem />}
                    sx={{ color: 'text.secondary', flexWrap: 'wrap', justifyContent: 'center', rowGap: 1 }}
                >
                    <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                        <CalendarTodayIcon fontSize="small" />
                        <Typography variant="body2">{scheduledAt}</Typography>
                    </Stack>
                    <Stack direction="row" spacing={0.5} sx={{ alignItems: 'center' }}>
                        <PlaceIcon fontSize="small" />
                        <Typography variant="body2">{dealershipAddress}</Typography>
                    </Stack>
                </Stack>
                <Button component={RouterLink} to="/" variant="contained" sx={{ width: '100%' }}>
                    Outros veículos
                </Button>
            </Stack>
        );
    }

    return (
        <Stack spacing={2}>
            <Typography variant="subtitle1">Agendar visita</Typography>

            <DateTimeCarousel
                vehicleId={vehicleId}
                date={date}
                onDateChange={setDate}
                time={time}
                onTimeChange={setTime}
            />

            <TextField
                label="Nome"
                value={customerName}
                onChange={(event) => setCustomerName(event.target.value)}
                required
            />
            <TextField
                label="E-mail"
                type="email"
                value={customerEmail}
                onChange={(event) => setCustomerEmail(event.target.value)}
                required
            />
            <TextField
                label="Telefone"
                value={customerPhone}
                onChange={(event) => setCustomerPhone(event.target.value)}
                required
            />

            {bookingMutation.isError && (
                <Alert severity="error">
                    {conflictError
                        ? 'Esse horário acabou de ser reservado por outra pessoa -- escolha outro.'
                        : 'Não foi possível criar o agendamento.'}
                </Alert>
            )}

            <Button
                variant="contained"
                disabled={!canSubmit || bookingMutation.isPending}
                onClick={() => bookingMutation.mutate()}
            >
                Confirmar agendamento
            </Button>
        </Stack>
    );
}
