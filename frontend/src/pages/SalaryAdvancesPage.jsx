import { useEffect, useState } from 'react';
import useLayoutStore from '../store/useLayoutStore';
import api from '../services/api';
import SalaryAdvances from '../components/SalaryAdvances';
import EmployeeAdvances from './EmployeeAdvances';

const SalaryAdvancesPage = () => {
    const viewMode = localStorage.getItem('adminViewMode') || 'admin';

    if (viewMode === 'employee') {
        return <EmployeeAdvances />;
    }

    const { setPageTitle, setPageSubtitle, resetPageHeader } = useLayoutStore();
    const [companies, setCompanies] = useState([]);

    useEffect(() => {
        setPageTitle("Salary Advances");
        setPageSubtitle("Manage employee salary advances and deductions");
        fetchCompanies();
        return () => resetPageHeader();
    }, []);

    const fetchCompanies = async () => {
        try {
            const res = await api.get('/organization/companies');
            if (res.data.success || res.data.status === 'success') {
                setCompanies(res.data.data || res.data);
            }
        } catch (error) {
            console.error('Error fetching companies:', error);
        }
    };

    return (
        <div>
            <SalaryAdvances companies={companies} />
        </div>
    );
};

export default SalaryAdvancesPage;
