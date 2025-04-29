import React, { useState, useEffect } from 'react';
import { Button, Form, Container, Card, Spinner, Table, Alert } from 'react-bootstrap';
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function Chatbot() {
    const [question, setQuestion] = useState('');
    const [answer, setAnswer] = useState('');
    const [typedAnswer, setTypedAnswer] = useState(''); 
    const [citations, setCitations] = useState([]);
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
                setAnswer(response.data.answer);
                setCitations(response.data.citations || []);
            } else {
                setError("Unable to fetch answer.");
            }
        } catch (error) {
            console.error("Error connecting to server:", error);
            setError("Failed to fetch answer.");
        }
        setLoading(false);
    };

    // Typing effect
    useEffect(() => {
        if (!answer) return;

        let currentIndex = 0;
        const typingSpeed = 30; // milliseconds between each character
        const interval = setInterval(() => {
            setTypedAnswer(prev => prev + answer[currentIndex]);
            currentIndex++;
            if (currentIndex >= answer.length) {
                clearInterval(interval);
            }
        }, typingSpeed);

        return () => clearInterval(interval); 
    }, [answer]);

    return (
        <Container className="py-4">
            <Card className="p-4 shadow-sm">
                <Card.Title className="mb-3">Ask the Chatbot</Card.Title>
                <Form>
                    <Form.Group controlId="question">
                        <Form.Label>Your Question</Form.Label>
                        <Form.Control 
                            as="textarea" 
                            rows={3} 
                            value={question} 
                            onChange={e => setQuestion(e.target.value)}
                            placeholder="Type your question here..."
                        />
                    </Form.Group>
                    <div className="d-flex justify-content-end mt-3">
                        <Button variant="primary" onClick={fetchAnswer} disabled={!question || loading}>
                            {loading ? (
                                <>
                                    <Spinner animation="border" size="sm" className="me-2" />
                                    Fetching Answer...
                                </>
                            ) : (
                                "Get Answer"
                            )}
                        </Button>
                    </div>
                </Form>
            </Card>

            <div className="mt-4">
                {error && (
                    <Alert variant="danger">
                        {error}
                    </Alert>
                )}

                {typedAnswer && (
                    <Card className="mt-3 p-4 shadow-sm">
                        <Card.Title>Answer</Card.Title>
                        <Card.Text style={{ whiteSpace: 'pre-wrap' }}>
                            {typedAnswer}
                        </Card.Text>

                        {citations.length > 0 && (
                            <>
                                <h6>Citations:</h6>
                                <Table striped bordered hover size="sm">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Source</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {citations.map((citation, index) => (
                                            <tr key={index}>
                                                <td>{index + 1}</td>
                                                <td>{citation}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </Table>
                            </>
                        )}
                    </Card>
                )}
            </div>
        </Container>
    );
}

export default Chatbot;
