import Autocomplete from '@mui/material/Autocomplete';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Grid from '@mui/material/Grid';
import InputAdornment from '@mui/material/InputAdornment';
import Button from '@mui/material/Button';
import { useQuery } from '@tanstack/react-query';
import { useEffect, useState, type FormEvent } from 'react';
import { ResponsiveDialog as Dialog } from './ResponsiveDialog';
import { FormError } from './FormError';
import { FormTextField } from './FormTextField';
import { SubmitButton } from './SubmitButton';
import { apiFetch, ApiError } from '../lib/apiClient';
import { listSellers, type UserProfile } from '../lib/auth';
import { BRAZILIAN_STATES, type BrazilianState } from '../lib/brazilianStates';
import type { Dealership, DealershipProfileInput } from '../lib/dealerships';

const emptyForm: DealershipProfileInput = {
    name: '',
    zip_code: '',
    address: '',
    number: '',
    complement: '',
    neighborhood: '',
    city: '',
    state: '',
    phone: '',
    email: '',
    owner_user_id: '',
};

interface ZipCodeAddress {
    street: string;
    neighborhood: string;
    city: string;
    state: string;
}

/** Proxy do próprio backend pro ViaCEP (cacheado depois da primeira consulta) -- nunca chama o terceiro direto. */
async function fetchAddressByZipCode(zipCode: string): Promise<ZipCodeAddress | null> {
    const digits = zipCode.replace(/\D/g, '');

    if (digits.length !== 8) {
        return null;
    }

    return apiFetch<ZipCodeAddress>(`/zip-codes/${digits}`).catch(() => null);
}

function formFromDealership(dealership?: Dealership | null): DealershipProfileInput {
    if (!dealership) {
        return emptyForm;
    }

    return {
        name: dealership.name,
        zip_code: dealership.zip_code,
        address: dealership.address,
        number: dealership.number,
        complement: dealership.complement ?? '',
        neighborhood: dealership.neighborhood,
        city: dealership.city,
        state: dealership.state,
        phone: dealership.phone ?? '',
        email: dealership.email ?? '',
        owner_user_id: dealership.owner_user_id,
    };
}

interface DealershipFormDialogProps {
    open: boolean;
    /** Presente = editando essa concessionária; ausente = criando uma nova. */
    dealership?: Dealership | null;
    /** Admin manda `owner_user_id` (obrigatório ao criar); seller nunca vê esse campo, o backend o torna dono sozinho. */
    isAdmin: boolean;
    /** Telefone/e-mail do próprio usuário logado -- preenchem os campos da concessionária com um clique. */
    myPhone?: string | null;
    myEmail?: string | null;
    submitting: boolean;
    error: ApiError | null;
    onSubmit: (input: DealershipProfileInput) => void;
    onClose: () => void;
}

/**
 * Mesmo formulário serve criação e edição -- `dealership` presente só
 * pré-preenche os campos. O pai remonta este componente (via `key`) toda vez
 * que troca o alvo, então o estado inicial já nasce certo, sem precisar de
 * um efeito só pra resincronizar quando `dealership` muda.
 */
export function DealershipFormDialog({
    open,
    dealership,
    isAdmin,
    myPhone,
    myEmail,
    submitting,
    error,
    onSubmit,
    onClose,
}: DealershipFormDialogProps) {
    const [form, setForm] = useState<DealershipProfileInput>(() => formFromDealership(dealership));

    // Catálogo de vendedores só é buscado quando o campo aparece (admin) -- `enabled` evita
    // uma chamada 403 inútil quando quem abre o diálogo é o próprio seller.
    const sellers = useQuery({
        queryKey: ['users', 'sellers', 'for-dealership-form'],
        queryFn: listSellers,
        enabled: isAdmin,
    });
    const sellerOptions = sellers.data ?? [];
    const selectedSeller = sellerOptions.find((seller) => seller.id === form.owner_user_id) ?? null;

    function set<K extends keyof DealershipProfileInput>(field: K, value: DealershipProfileInput[K]) {
        setForm((current) => ({ ...current, [field]: value }));
    }

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        onSubmit(form);
    }

    // CEP completo -- espera meio segundo sem digitar (debounce) e autopreenche o
    // resto do endereço sozinho, sem travar o formulário se a busca falhar.
    useEffect(() => {
        if (form.zip_code.replace(/\D/g, '').length !== 8) {
            return;
        }

        const timeout = setTimeout(() => {
            fetchAddressByZipCode(form.zip_code)
                .then((address) => {
                    if (!address) {
                        return;
                    }

                    setForm((current) => ({
                        ...current,
                        address: address.street || current.address,
                        neighborhood: address.neighborhood || current.neighborhood,
                        city: address.city,
                        state: address.state,
                    }));
                })
                .catch(() => undefined);
        }, 500);

        return () => clearTimeout(timeout);
    }, [form.zip_code]);

    const selectedState = BRAZILIAN_STATES.find((state) => state.code === form.state) ?? null;

    return (
        <Dialog open={open} onClose={onClose} maxWidth="sm" fullWidth>
            <DialogTitle>{dealership ? 'Editar concessionária' : 'Nova concessionária'}</DialogTitle>
            <form onSubmit={handleSubmit}>
                <DialogContent>
                    <Grid container spacing={1}>
                        <Grid size={12}>
                            <FormTextField
                                label="Nome"
                                value={form.name}
                                onChange={(event) => set('name', event.target.value)}
                                error={error?.errors?.name}
                                required
                            />
                        </Grid>
                        {isAdmin && (
                            <Grid size={12}>
                                <Autocomplete<UserProfile>
                                    options={sellerOptions}
                                    loading={sellers.isPending}
                                    getOptionLabel={(seller) => `${seller.name} (${seller.email})`}
                                    value={selectedSeller}
                                    onChange={(_, seller) => set('owner_user_id', seller?.id ?? '')}
                                    isOptionEqualToValue={(option, selected) => option.id === selected.id}
                                    renderInput={(params) => (
                                        <FormTextField
                                            {...params}
                                            label="Dono"
                                            error={error?.errors?.owner_user_id}
                                            helperText={
                                                error?.errors?.owner_user_id ?? 'Vendedor dono desta concessionária'
                                            }
                                            required
                                        />
                                    )}
                                />
                            </Grid>
                        )}
                        <Grid size={12}>
                            <FormTextField
                                label="CEP"
                                value={form.zip_code}
                                onChange={(event) => set('zip_code', event.target.value)}
                                error={error?.errors?.zip_code}
                                helperText={error?.errors?.zip_code ?? 'Preenche endereço, bairro, cidade e UF sozinho'}
                                required
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="Telefone"
                                value={form.phone}
                                onChange={(event) => set('phone', event.target.value)}
                                error={error?.errors?.phone}
                                slotProps={{
                                    input: {
                                        endAdornment: myPhone ? (
                                            <InputAdornment position="end">
                                                <Button
                                                    size="small"
                                                    aria-label="Usar o meu telefone"
                                                    onClick={() => set('phone', myPhone)}
                                                >
                                                    Usar o meu
                                                </Button>
                                            </InputAdornment>
                                        ) : undefined,
                                    },
                                }}
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="E-mail"
                                type="email"
                                value={form.email}
                                onChange={(event) => set('email', event.target.value)}
                                error={error?.errors?.email}
                                slotProps={{
                                    input: {
                                        endAdornment: myEmail ? (
                                            <InputAdornment position="end">
                                                <Button
                                                    size="small"
                                                    aria-label="Usar o meu e-mail"
                                                    onClick={() => set('email', myEmail)}
                                                >
                                                    Usar o meu
                                                </Button>
                                            </InputAdornment>
                                        ) : undefined,
                                    },
                                }}
                            />
                        </Grid>
                        <Grid size={8}>
                            <FormTextField
                                label="Endereço"
                                value={form.address}
                                onChange={(event) => set('address', event.target.value)}
                                error={error?.errors?.address}
                                required
                            />
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                label="Número"
                                value={form.number}
                                onChange={(event) => set('number', event.target.value.replace(/\D/g, ''))}
                                error={error?.errors?.number}
                                slotProps={{ htmlInput: { inputMode: 'numeric', pattern: '[0-9]*' } }}
                                required
                            />
                        </Grid>
                        <Grid size={12}>
                            <FormTextField
                                label="Complemento"
                                value={form.complement}
                                onChange={(event) => set('complement', event.target.value)}
                                error={error?.errors?.complement}
                            />
                        </Grid>
                        <Grid size={6}>
                            <FormTextField
                                label="Bairro"
                                value={form.neighborhood}
                                onChange={(event) => set('neighborhood', event.target.value)}
                                error={error?.errors?.neighborhood}
                                required
                            />
                        </Grid>
                        <Grid size={4}>
                            <FormTextField
                                label="Cidade"
                                value={form.city}
                                onChange={(event) => set('city', event.target.value)}
                                error={error?.errors?.city}
                                required
                            />
                        </Grid>
                        <Grid size={2}>
                            <Autocomplete<BrazilianState>
                                options={BRAZILIAN_STATES}
                                getOptionLabel={(state) => state.code}
                                value={selectedState}
                                onChange={(_, state) => set('state', state?.code ?? '')}
                                isOptionEqualToValue={(option, value) => option.code === value.code}
                                // O campo é estreito (só cabe "UF") de propósito -- o dropdown não pode
                                // herdar essa largura, senão "AC -- Acre" quebra em várias linhas.
                                slotProps={{ popper: { style: { width: 240 } } }}
                                renderOption={(props, state) => (
                                    <li {...props} key={state.code}>
                                        {state.code} -- {state.name}
                                    </li>
                                )}
                                renderInput={(params) => (
                                    <FormTextField {...params} label="UF" error={error?.errors?.state} required />
                                )}
                            />
                        </Grid>
                    </Grid>
                    <FormError message={error?.message} />
                </DialogContent>
                <DialogActions sx={{ px: 3, pb: 2 }}>
                    <SubmitButton loading={submitting} sx={{ width: 'auto' }}>
                        {dealership ? 'Salvar' : 'Criar'}
                    </SubmitButton>
                </DialogActions>
            </form>
        </Dialog>
    );
}
