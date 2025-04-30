import React, { useEffect, useState } from "react";
import axios from "axios";
import Navbar from "../Components/Navbar";
import getBackendURL from "../Utils/backendURL";
import { useNavigate } from "react-router-dom";

function NewsPage({ handleLogout }) {
    const [newsData, setNewsData] = useState([]);
    const [error, setError] = useState("");
    const [loading, setLoading] = useState(true);
    const navigate = useNavigate();

    useEffect(() => {
        const fetchNews = async () => {
            try {
                const response = await axios.post(getBackendURL(), {
                    type: "GET_NEWS"
                }, { withCredentials: true });

                if (
                    response.status === 200 &&
                    response.data.status === "SUCCESS" &&
                    response.data.data &&
                    response.data.data.articles
                ) {
                    setNewsData(response.data.data.articles);
                } else {
                    setError(response.data.data?.message || "No news articles found.");
                }
            } catch (err) {
                console.error("Error fetching news:", err);
                setError("Failed to fetch news.");
            } finally {
                setLoading(false);
            }
        };

        fetchNews();
    }, []);

    return (
        <div>
            <Navbar handleLogout={handleLogout} />
            <h1>Latest News</h1>

            {loading && <p>Loading news...</p>}
            {error && <p style={{ color: "red" }}>{error}</p>}

            {newsData.length > 0 && (
                <div>
                    {newsData.map((article, index) => (
                        <div key={index} style={{ border: "1px solid #ddd", padding: "12px", marginBottom: "10px" }}>
                            <h3>{article.title}</h3>
                            {article.description && <p>{article.description}</p>}
                            <p><strong>Source:</strong> {article.source || "Unknown"}</p>
                            <a href={article.url} target="_blank" rel="noopener noreferrer">Read more</a>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default NewsPage;
