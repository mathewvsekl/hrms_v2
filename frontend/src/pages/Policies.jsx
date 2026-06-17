import { getSecureMediaUrl } from '../utils/mediaHelper';
import { useState, useEffect } from 'react';
import { FileText, Download, Search, Filter, BookOpen, Shield, HelpCircle, Loader } from 'lucide-react';
import api from '../services/api';
import { formatDate } from '../utils/dateUtils';

const Policies = () => {
    const [documents, setDocuments] = useState([]);
    const [loading, setLoading] = useState(true);
    const [searchTerm, setSearchTerm] = useState('');
    const [activeCategory, setActiveCategory] = useState('All');

    useEffect(() => {
        fetchDocuments();
    }, []);

    const fetchDocuments = async () => {
        try {
            setLoading(true);
            const response = await api.get('/company-documents');
            setDocuments(response.data?.data || []);
        } catch (error) {
            console.error("Failed to fetch reference documents", error);
        } finally {
            setLoading(false);
        }
    };

    const categories = ['All', ...new Set(documents.map(d => d.category))];

    const filteredDocs = documents.filter(doc => {
        const matchesSearch = doc.document_name.toLowerCase().includes(searchTerm.toLowerCase());
        const matchesCategory = activeCategory === 'All' || doc.category === activeCategory;
        return matchesSearch && matchesCategory;
    });

    const getCategoryIcon = (category) => {
        switch (category?.toLowerCase()) {
            case 'law': return <Shield size={20} />;
            case 'policy': return <BookOpen size={20} />;
            case 'manual': return <FileText size={20} />;
            default: return <HelpCircle size={20} />;
        }
    };

    const getCategoryColor = (category) => {
        switch (category?.toLowerCase()) {
            case 'law': return '#ef4444'; // Red
            case 'policy': return '#3b82f6'; // Blue
            case 'manual': return '#10b981'; // Green
            default: return '#6b7280'; // Gray
        }
    };

    if (loading) {
        return (
            <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', height: '60vh' }}>
                <Loader className="spin" size={32} color="var(--primary-brand)" />
            </div>
        );
    }

    return (
        <div className="policies-container">
            <style>{`
                .policies-header {
                    margin-bottom: 2rem;
                }
                .policies-header h1 {
                    font-family: var(--font-heading);
                    font-size: 32px;
                    margin-bottom: 8px;
                    color: var(--text-primary);
                }
                .policies-header p {
                    color: var(--text-secondary);
                    font-size: 16px;
                }
                .filters-bar {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 2rem;
                    gap: 1rem;
                    flex-wrap: wrap;
                }
                .search-box {
                    position: relative;
                    flex: 1;
                    min-width: 300px;
                }
                .search-box input {
                    width: 100%;
                    padding: 12px 12px 12px 40px;
                    border: 1px solid var(--border-gray);
                    border-radius: 10px;
                    font-size: 14px;
                    background: #fff;
                    transition: border-color 0.2s;
                }
                .search-box input:focus {
                    border-color: var(--primary-brand);
                    outline: none;
                }
                .search-box .search-icon {
                    position: absolute;
                    left: 14px;
                    top: 50%;
                    transform: translateY(-50%);
                    color: #94a3b8;
                }
                .category-tabs {
                    display: flex;
                    gap: 8px;
                }
                .category-tab {
                    padding: 8px 16px;
                    border-radius: 8px;
                    font-size: 14px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s;
                    border: 1px solid transparent;
                    background: #f1f5f9;
                    color: #64748b;
                }
                .category-tab.active {
                    background: var(--primary-brand);
                    color: white;
                }
                .docs-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 1.5rem;
                }
                .doc-card {
                    background: white;
                    border: 1px solid var(--border-gray);
                    border-radius: 12px;
                    padding: 1.5rem;
                    transition: all 0.2s;
                    display: flex;
                    flex-direction: column;
                    gap: 1rem;
                    position: relative;
                    overflow: hidden;
                }
                .doc-card:hover {
                    transform: translateY(-4px);
                    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
                    border-color: var(--primary-brand);
                }
                .doc-category-tag {
                    font-size: 10px;
                    font-weight: 800;
                    text-transform: uppercase;
                    padding: 4px 8px;
                    border-radius: 6px;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    width: fit-content;
                }
                .doc-title {
                    font-size: 18px;
                    font-weight: 700;
                    color: var(--text-primary);
                    margin: 0;
                    line-height: 1.4;
                }
                .doc-meta {
                    font-size: 12px;
                    color: var(--text-secondary);
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                }
                .doc-actions {
                    margin-top: auto;
                    display: flex;
                    justify-content: flex-end;
                }
                .download-btn {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 8px 16px;
                    background: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 8px;
                    color: var(--primary-brand);
                    font-weight: 700;
                    font-size: 13px;
                    cursor: pointer;
                    transition: all 0.2s;
                    text-decoration: none;
                }
                .download-btn:hover {
                    background: var(--primary-brand);
                    color: white;
                    border-color: var(--primary-brand);
                }
                .empty-state {
                    text-align: center;
                    padding: 4rem 2rem;
                    background: #f8fafc;
                    border-radius: 16px;
                    border: 2px dashed #e2e8f0;
                    color: #64748b;
                }
            `}</style>

            <div className="policies-header">
                <h1>Policies & Reference</h1>
                <p>Access important legal documents, company manuals, and operating policies.</p>
            </div>

            <div className="filters-bar">
                <div className="search-box">
                    <Search className="search-icon" size={18} />
                    <input 
                        type="text" 
                        placeholder="Search documents by name..." 
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                    />
                </div>
                <div className="category-tabs">
                    {categories.map(cat => (
                        <div 
                            key={cat}
                            className={`category-tab ${activeCategory === cat ? 'active' : ''}`}
                            onClick={() => setActiveCategory(cat)}
                        >
                            {cat}
                        </div>
                    ))}
                </div>
            </div>

            {filteredDocs.length > 0 ? (
                <div className="docs-grid">
                    {filteredDocs.map(doc => (
                        <div key={doc.id} className="doc-card">
                            <div className="doc-category-tag" style={{ 
                                background: getCategoryColor(doc.category) + '15', 
                                color: getCategoryColor(doc.category) 
                            }}>
                                {getCategoryIcon(doc.category)}
                                {doc.category}
                            </div>
                            <h3 className="doc-title">{doc.document_name}</h3>
                            <div className="doc-meta">
                                <span>Published: {formatDate(doc.created_at_utc)}</span>
                                {doc.company_name && <span style={{ fontWeight: 600 }}>Office: {doc.company_name}</span>}
                                {doc.country_name && <span style={{ fontWeight: 600 }}>Region: {doc.country_name}</span>}
                                {!doc.company_name && !doc.country_name && <span style={{ color: '#8b5cf6', fontWeight: 600 }}>Global Resource</span>}
                            </div>
                            <div className="doc-actions">
                                <a 
                                    href={getSecureMediaUrl(doc.file_path)} 
                                    target="_blank" 
                                    rel="noopener noreferrer"
                                    className="download-btn"
                                >
                                    <Download size={16} /> View Document
                                </a>
                            </div>
                        </div>
                    ))}
                </div>
            ) : (
                <div className="empty-state">
                    <FileText size={48} style={{ marginBottom: '1rem', opacity: 0.5 }} />
                    <h3>No documents found</h3>
                    <p>Try adjusting your search or category filters.</p>
                </div>
            )}
        </div>
    );
};

export default Policies;
