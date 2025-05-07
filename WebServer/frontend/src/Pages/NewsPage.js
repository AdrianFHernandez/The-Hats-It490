import React, { useEffect, useState } from "react";
import { Container, Card, Spinner, Alert, Row, Col, Form, Button } from "react-bootstrap";
import axios from "axios";
import Navbar from "../Components/Navbar";
import getBackendURL from "../Utils/backendURL";
import { useNavigate } from "react-router-dom";

function NewsPage({ handleLogout }) {
    const [newsData, setNewsData] = useState([]);
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState("stock market");
    const navigate = useNavigate();

    const fetchNews = async () => {
        setLoading(true);
        setError("");
        try {
            const response = await axios.post(
                getBackendURL(),
                {
                    type: "GET_NEWS",
                    query: searchTerm
                },
                { withCredentials: true }
            );

            if (
                response.status === 200 &&
                response.data.news &&
                response.data.news.articles
            ) {
                setNewsData(response.data.news.articles);
            } else {
                setError(response.data.news?.message || "No news articles found.");
            }
        } catch (err) {
            console.error("Error fetching news:", err);
            setError("Failed to fetch news.");
        } finally {
            setLoading(false);
        }
    };

    // Optionally fetch default on mount
    useEffect(() => {
        fetchNews();
    }, []);

    return (
        <div>
            <Navbar handleLogout={handleLogout} />

            <Container className="py-4">
                <h2 className="mb-4 text-white">📰 Latest News</h2>

                <Form className="mb-4" onSubmit={e => { e.preventDefault(); fetchNews(); }}>
                    <Form.Group controlId="searchTerm">
                        <Form.Control
                            type="text"
                            placeholder="Search news topic (e.g., AI, economy, space)"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </Form.Group>
                    <Button type="submit" className="mt-2" disabled={loading}>
                        {loading ? "Searching..." : "Search"}
                    </Button>
                </Form>

                {loading && (
                    <div className="text-center my-4">
                        <Spinner animation="border" role="status" />
                        <p className="mt-2">Loading news...</p>
                    </div>
                )}

                {error && <Alert variant="danger">{error}</Alert>}

                <Row>
                    {newsData.length > 0 &&
                        newsData.map((article, index) => (
                            <Col key={index} xs={12} className="mb-3">
                                <Card className="shadow-sm h-100">
                                    <Card.Body>
                                        <Card.Title>{article.title}</Card.Title>
                                        {article.description && (
                                            <Card.Text>{article.description}</Card.Text>
                                        )}
                                        <Card.Text className="text-muted">
                                            <strong>Source:</strong> {article.source || "Unknown"}
                                        </Card.Text>
                                        <a
                                            href={article.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="btn btn-outline-primary btn-sm"
                                        >
                                            Read more
                                        </a>
                                    </Card.Body>
                                </Card>
                            </Col>
                        ))}
                </Row>
            </Container>
        </div>
    );
}

export default NewsPage;
