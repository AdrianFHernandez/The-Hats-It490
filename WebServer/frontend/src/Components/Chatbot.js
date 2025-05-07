import React, { useState, useEffect } from 'react';
import { Button, Form, Container, Card, Spinner, Table, Alert } from 'react-bootstrap';
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function Chatbot() {
    const [question, setQuestion] = useState('');
    const [answer, setAnswer] = useState('');
    const [typedAnswer, setTypedAnswer] = useState(''); 
    // const [citations, setCitations] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchAnswer = async () => {
        setLoading(true);
        setError(null);
        setTypedAnswer(''); 

        try {
            const response = await axios.post(
                getBackendURL(), 
                { type: "GET_CHATBOT_ANSWER", question }, 
                { withCredentials: true }
            );

            if (response.status === 200 && response.data) {
                console.log(response.data);
                setAnswer(response.data.answer);
                // setCitations(response.data.citations || []);
            } else {
                setError("Unable to fetch answer.");
            }
        } catch (error) {
            console.error("Error connecting to server:", error);
            setError("Failed to fetch answer.");
        }
        setLoading(false);
    };

    useEffect(() => {
        if (!answer) return;
    
        let currentIndex = 0;
        setTypedAnswer(answer[0] || ""); // set first character immediately
    
        const typingSpeed = 30;
    
        const interval = setInterval(() => {
            currentIndex++;
            if (currentIndex < answer.length) {
                setTypedAnswer(prev => prev + answer[currentIndex]);
            } else {
                clearInterval(interval);
            }
        }, typingSpeed);
    
        return () => clearInterval(interval);
    }, [answer]);
    
    return (
        <Container className="py-4 d-flex justify-content-center">
            <Card className="w-100 shadow-sm" style={{ maxWidth: '600px', height: '75vh', display: 'flex', flexDirection: 'column' }}>
                <Card.Header className="bg-primary text-white">
                    💬 Ask the Chatbot
                </Card.Header>
    
                <Card.Body className="flex-grow-1 overflow-auto p-3 d-flex flex-column" style={{ backgroundColor: '#f8f9fa' }}>
                    {question && (
                        <div className="align-self-end bg-success text-white p-2 rounded mb-2" style={{ maxWidth: '75%' }}>
                            <strong>You:</strong> <br />{question}
                        </div>
                    )}
    
                    {typedAnswer && (
                        <div className="align-self-start bg-light p-2 rounded mb-2 border" style={{ maxWidth: '75%' }}>
                            <strong>Bot:</strong> <br />{typedAnswer}
                        </div>
                    )}
    
                    {error && (
                        <Alert variant="danger" className="mt-2">{error}</Alert>
                    )}
                </Card.Body>
    
                <Card.Footer className="p-3">
                    <Form>
                        <Form.Group controlId="question">
                            <Form.Control
                                as="textarea"
                                rows={2}
                                value={question}
                                onChange={(e) => setQuestion(e.target.value)}
                                placeholder="Type your question..."
                                className="mb-2"
                            />
                        </Form.Group>
                        <div className="d-flex justify-content-end">
                            <Button variant="primary" onClick={fetchAnswer} disabled={!question || loading}>
                                {loading ? (
                                    <>
                                        <Spinner animation="border" size="sm" className="me-2" />
                                        Sending...
                                    </>
                                ) : (
                                    'Send'
                                )}
                            </Button>
                        </div>
                    </Form>
                </Card.Footer>
            </Card>
        </Container>
    );
    
}

export default Chatbot;
