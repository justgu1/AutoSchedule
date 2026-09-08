import { Route, Routes } from 'react-router-dom';
import { AuthLayout } from './layouts/AuthLayout';
import { AuthenticatedLayout } from './layouts/AuthenticatedLayout';
import { PublicLayout } from './layouts/PublicLayout';
import { DealershipsPage } from './pages/DealershipsPage';
import { ForgotPasswordPage } from './pages/ForgotPasswordPage';
import { HomePage } from './pages/HomePage';
import { LoginPage } from './pages/LoginPage';
import { MePage } from './pages/MePage';
import { PublicDealershipPage } from './pages/PublicDealershipPage';
import { PublicVehiclePage } from './pages/PublicVehiclePage';
import { RegisterPage } from './pages/RegisterPage';
import { ResetPasswordPage } from './pages/ResetPasswordPage';
import { VehiclesPage } from './pages/VehiclesPage';

export default function App() {
    return (
        <Routes>
            <Route element={<PublicLayout />}>
                <Route path="/" element={<HomePage />} />
                <Route path="/concessionarias/:slug" element={<PublicDealershipPage />} />
                <Route path="/veiculos/:id" element={<PublicVehiclePage />} />
            </Route>
            <Route element={<AuthLayout />}>
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />
                <Route path="/forgot-password" element={<ForgotPasswordPage />} />
                <Route path="/reset-password" element={<ResetPasswordPage />} />
            </Route>
            <Route element={<AuthenticatedLayout />}>
                <Route path="/me" element={<MePage />} />
                <Route path="/dealerships" element={<DealershipsPage />} />
                <Route path="/vehicles" element={<VehiclesPage />} />
            </Route>
        </Routes>
    );
}
