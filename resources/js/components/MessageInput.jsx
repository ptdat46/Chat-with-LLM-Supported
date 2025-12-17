
import React, { useState } from "react";

const MessageInput = ({ rootUrl }) => {
    const [message, setMessage] = useState("");
    const [suggestions, setSuggestions] = useState(null);
    const [loadingSuggestions, setLoadingSuggestions] = useState(false);

    const messageRequest = async (text) => {
        try {
            await axios.post(`${rootUrl}/message`, {
                text,
            });
        } catch (err) {
            console.log(err.message);
        }
    };

    const sendMessage = (e) => {
        e.preventDefault();
        if (message.trim() === "") {
            alert("Please enter a message!");
            return;
        }

        messageRequest(message);
        setMessage("");
        setSuggestions(null); // Clear suggestions after sending
    };

    const getSuggestions = async () => {
        setLoadingSuggestions(true);
        try {
            const response = await axios.get(`${rootUrl}/message-suggestions`);
            if (response.data.success) {
                setSuggestions(response.data.suggestions);
            } else {
                alert(response.data.message || 'Không thể lấy gợi ý');
            }
        } catch (err) {
            console.error('Error getting suggestions:', err);
            alert('Lỗi khi lấy gợi ý từ AI');
        } finally {
            setLoadingSuggestions(false);
        }
    };

    const useSuggestion = (suggestionText) => {
        setMessage(suggestionText);
        setSuggestions(null);
    };

    return (
        <div>
            {/* Suggestions Panel */}
            {suggestions && (
                <div className="card mb-2" style={{ backgroundColor: '#f8f9fa' }}>
                    <div className="card-body py-2">
                        <div className="d-flex justify-content-between align-items-center mb-2">
                            <small className="text-muted">
                                <i className="fas fa-magic"></i> Gợi ý từ AI
                            </small>
                            <button 
                                className="btn btn-sm btn-link text-muted p-0"
                                onClick={() => setSuggestions(null)}
                            >
                                <i className="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <div className="row">
                            <div className="col-md-6 mb-2">
                                <div 
                                    className="suggestion-card p-2 border rounded"
                                    style={{ cursor: 'pointer', backgroundColor: 'white' }}
                                    onClick={() => useSuggestion(suggestions.cheerful)}
                                >
                                    <div className="d-flex align-items-center mb-1">
                                        <span className="badge badge-success mr-2">😊 Vui vẻ</span>
                                    </div>
                                    <small>{suggestions.cheerful}</small>
                                </div>
                            </div>
                            
                            <div className="col-md-6 mb-2">
                                <div 
                                    className="suggestion-card p-2 border rounded"
                                    style={{ cursor: 'pointer', backgroundColor: 'white' }}
                                    onClick={() => useSuggestion(suggestions.professional)}
                                >
                                    <div className="d-flex align-items-center mb-1">
                                        <span className="badge badge-primary mr-2">💼 Nghiêm túc</span>
                                    </div>
                                    <small>{suggestions.professional}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            {/* Input Group */}
            <div className="input-group">
                <input 
                    onChange={(e) => setMessage(e.target.value)}
                    autoComplete="off"
                    type="text"
                    className="form-control"
                    placeholder="Message..."
                    value={message}
                    onKeyPress={(e) => {
                        if (e.key === 'Enter') {
                            sendMessage(e);
                        }
                    }}
                />
                <div className="input-group-append">
                    <button 
                        onClick={getSuggestions}
                        className="btn btn-outline-secondary"
                        type="button"
                        disabled={loadingSuggestions}
                        title="Gợi ý từ AI"
                    >
                        {loadingSuggestions ? (
                            <span className="spinner-border spinner-border-sm"></span>
                        ) : (
                            <i className="fas fa-magic"></i>
                        )}
                    </button>
                    <button 
                        onClick={(e) => sendMessage(e)}
                        className="btn btn-primary"
                        type="button"
                    >
                        Send
                    </button>
                </div>
            </div>
        </div>
    );
};

export default MessageInput;
