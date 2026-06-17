import React from 'react';
import { AlertTriangle, RefreshCw, Home, ChevronDown, ChevronUp } from 'lucide-react';

class ErrorBoundary extends React.Component {
  constructor(props) {
    super(props);
    this.state = { 
      hasError: false, 
      error: null, 
      errorInfo: null,
      showDetails: false 
    };
  }

  static getDerivedStateFromError(error) {
    // Update state so the next render will show the fallback UI.
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    // You can also log the error to an error reporting service
    console.error("Uncaught error:", error, errorInfo);
    this.setState({ errorInfo });
  }

  handleReset = () => {
    this.setState({ hasError: false, error: null, errorInfo: null });
    window.location.reload();
  };

  handleGoHome = () => {
    this.setState({ hasError: false, error: null, errorInfo: null });
    window.location.href = '/dashboard';
    window.location.reload();
  };

  toggleDetails = () => {
    this.setState(prev => ({ showDetails: !prev }));
  };

  render() {
    if (this.state.hasError) {
      // You can render any custom fallback UI
      return (
        <div className="error-boundary-container">
          <div className="error-card">
            <div className="error-header">
              <div className="error-icon-wrapper">
                <AlertTriangle className="error-icon" size={48} />
              </div>
              <h1>Something went wrong</h1>
              <p>The application encountered an unexpected error and couldn't continue.</p>
            </div>

            <div className="error-actions">
              <button onClick={this.handleReset} className="btn-primary">
                <RefreshCw size={18} />
                Reload Page
              </button>
              <button onClick={this.handleGoHome} className="btn-secondary">
                <Home size={18} />
                Return to Dashboard
              </button>
            </div>

            {import.meta.env.DEV && (
              <div className="error-details-section">
                <button onClick={this.toggleDetails} className="details-toggle">
                  {this.state.showDetails ? <ChevronUp size={16} /> : <ChevronDown size={16} />}
                  {this.state.showDetails ? 'Hide Technical Details' : 'Show Technical Details'}
                </button>
                
                {this.state.showDetails && (
                  <div className="error-details-content">
                    <div className="error-message">
                      <strong>Error:</strong> {this.state.error?.toString()}
                    </div>
                    {this.state.errorInfo && (
                      <pre className="error-stack">
                        {this.state.errorInfo.componentStack}
                      </pre>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          <style jsx="true">{`
            .error-boundary-container {
              display: flex;
              align-items: center;
              justify-content: center;
              min-height: 400px;
              height: 100%;
              width: 100%;
              background: #f8fafc;
              padding: 2rem;
              font-family: 'Inter', system-ui, -apple-system, sans-serif;
            }
            .error-card {
              background: white;
              padding: 3rem;
              border-radius: 1rem;
              box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
              max-width: 600px;
              width: 100%;
              text-align: center;
            }
            .error-icon-wrapper {
              display: inline-flex;
              align-items: center;
              justify-content: center;
              width: 80px;
              height: 80px;
              background: #fee2e2;
              color: #ef4444;
              border-radius: 50%;
              margin-bottom: 1.5rem;
            }
            h1 {
              color: #1e293b;
              font-size: 1.875rem;
              font-weight: 700;
              margin-bottom: 0.75rem;
            }
            p {
              color: #64748b;
              font-size: 1.125rem;
              margin-bottom: 2rem;
            }
            .error-actions {
              display: flex;
              gap: 1rem;
              justify-content: center;
              margin-bottom: 2rem;
            }
            .btn-primary, .btn-secondary {
              display: flex;
              align-items: center;
              gap: 0.5rem;
              padding: 0.75rem 1.5rem;
              border-radius: 0.5rem;
              font-weight: 600;
              cursor: pointer;
              transition: all 0.2s;
              border: none;
            }
            .btn-primary {
              background: #2563eb;
              color: white;
            }
            .btn-primary:hover {
              background: #1d4ed8;
            }
            .btn-secondary {
              background: #f1f5f9;
              color: #475569;
            }
            .btn-secondary:hover {
              background: #e2e8f0;
            }
            .error-details-section {
              text-align: left;
              border-top: 1px solid #e2e8f0;
              padding-top: 1.5rem;
            }
            .details-toggle {
              display: flex;
              align-items: center;
              gap: 0.25rem;
              background: none;
              border: none;
              color: #64748b;
              font-size: 0.875rem;
              cursor: pointer;
              padding: 0;
              margin-bottom: 1rem;
            }
            .error-details-content {
              background: #f1f5f9;
              padding: 1rem;
              border-radius: 0.5rem;
              overflow-x: auto;
              max-height: 300px;
            }
            .error-message {
              color: #ef4444;
              font-family: monospace;
              margin-bottom: 0.5rem;
              font-size: 0.875rem;
            }
            .error-stack {
              color: #475569;
              font-size: 0.75rem;
              font-family: monospace;
              white-space: pre-wrap;
            }
          `}</style>
        </div>
      );
    }

    return this.props.children; 
  }
}

export default ErrorBoundary;
