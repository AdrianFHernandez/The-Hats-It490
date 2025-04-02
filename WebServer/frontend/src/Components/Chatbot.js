import React, { useState } from 'react';
import { Button, Form, Container, Table } from 'react-bootstrap';
import axios from "axios";
import getBackendURL from "../Utils/backendURL";

function Chatbot(){
    const [question, setQuestion] = useState('');
    const [answer, setAnswer] = useState('');
    const [citations, setCitations] = useState([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchAnswer = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await axios.post(
                getBackendURL(), 
                {withCredentials: true},
                {type : "GET_CHATBOT_ANSWER" , question : question}, 
            );
            
          if (response.status === 200 && response.data) {
              setAnswer(response.data.answer);
              setCitations(response.data.citations);
              console.log(response.data.citations);
          }else{
              setError("Unable to fetch answer"); 
          }
      } catch (error) {
        console.error("Error connecting to server:", error);
        setError("Failed to fetch answer.");
      }
      setLoading(false);
    };

    return (
        <Container>
            <Form>
                <Form.Group controlId="question">
                    <Form.Label>Ask a question:</Form.Label>
                    <Form.Control as="textarea" rows={3} value={question} onChange={e => setQuestion(e.target.value)} />
                </Form.Group>
                <Button variant="primary" onClick={fetchAnswer} disabled={!question}>
                    Get Answer
                </Button>
            </Form>

            {loading && <p>Loading...</p>}
            {error && <p style={{ color: 'red' }}>{error}</p>}
            {answer && (
                <div>
                    <h5>Answer:</h5>
                    <p>{answer}</p>
                </div>
            )}
        </Container>
    );
}
export default Chatbot;