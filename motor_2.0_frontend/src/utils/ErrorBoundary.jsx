import React, { Component } from "react";
import warning from "../utils/img/warning.png";

class ErrorBoundary extends Component {
  constructor(props) {
    super(props);
    this.state = { error: null, errorInfo: null };
  }

  componentDidCatch(error, errorInfo) {
    // Catch errors in any components below and re-render with error message
    this.setState({
      error: error,
      errorInfo: errorInfo,
    });
    // You can also log error messages to an error reporting service here
  }

  render() {
    if (this.state.errorInfo) {
      // Error path
      return (
        <div
          style={{
            color: "#5D5D5D",
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            justifyContent: "center",
            height: "100vh",
          }}
        >
          <div
            style={{
              display: "flex",
              alignItems: "center",
              flexDirection: "column",
              gap: "20px",
            }}
          >
            <img src={warning} alt="" style={{ height: "150px" }} />
            <h2 style={{ fontWeight: "bold", fontSize: "2rem" }}>
              Uh oh! Something went wrong...
            </h2>
          </div>
          <details
            style={{
              whiteSpace: "pre-wrap",
              marginTop: "0.5rem",
              border: "1px solid #E53E3E",
              borderRadius: "4px",
              padding: "1rem",
              textAlign: "left",
              overflow: "auto",
            }}
          >
            <summary
              style={{
                marginBottom: "0.5rem",
                fontSize: "1rem",
                cursor: "pointer",
                fontWeight: "bold",
              }}
            >
              Error details
            </summary>
            <div style={{ padding: "0.5rem" }}>
              <p
                style={{
                  marginBottom: "0.5rem",
                  fontSize: "1rem",
                  fontFamily: "monospace",
                }}
              >
                {this.state.error && this.state.error.toString()}
              </p>
              <details
                style={{
                  whiteSpace: "pre-wrap",
                  border: "1px solid #FCE7E7",
                  borderRadius: "4px",
                  padding: "0.5rem",
                }}
              >
                <summary
                  style={{
                    marginBottom: "0.5rem",
                    fontSize: "1rem",
                    cursor: "pointer",
                    fontWeight: "bold",
                  }}
                >
                  Component stack trace
                </summary>
                <p
                  style={{
                    marginTop: "0.5rem",
                    fontSize: "1rem",
                    fontFamily: "monospace",
                  }}
                >
                  {this.state.errorInfo.componentStack}
                </p>
              </details>
            </div>
          </details>
          <p
            style={{ marginTop: "1rem", fontSize: "1rem", textAlign: "center" }}
          >
            Don't worry, our developers have been notified and are frantically
            working to fix it!
          </p>
        </div>
      );
    }
    // Normally, just render children
    return this.props.children;
  }
}

export default ErrorBoundary;
