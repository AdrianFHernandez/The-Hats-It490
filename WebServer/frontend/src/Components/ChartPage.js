import React, { useEffect, useRef, useState } from "react";
import { createChart, CrosshairMode, LineStyle, CandlestickSeries } from "lightweight-charts";
import { useParams } from 'react-router-dom';
import axios from "axios";
import getBackendURL from "../Utils/backendURL";
import Transaction from "./Transaction";
import Navbar from "../Components/Navbar"; // Import Navbar

function ChartPage() {
    const { Ticker } = useParams();  
    const containerRef = useRef(null);
    const chartRef = useRef(null);
    const [chartData, setChartData] = useState([]);
    const [candlestickSeries, setCandlestickSeries] = useState(null);
    const [timeframe, setTimeframe] = useState("1m");
    const [selectedTicker, setSelectedTicker] = useState(Ticker);
    const [stockInfo, setStockInfo] = useState(null);

    const aggregationIntervals = {
        "1m": 60, "5m": 300, "30m": 1800, "1h": 3600, "1d": 86400
    };

    const fetchHistoricalData = async () => {
        if (!selectedTicker) return;

        try {
            const today = new Date();
            const finalEndTime = today;
            const finalStartTime = new Date(finalEndTime);
            finalStartTime.setDate(finalEndTime.getDate() - 5);

            const formattedStart = finalStartTime.toISOString().split("T")[0];
            const formattedEnd = finalEndTime.toISOString().split("T")[0];

            const response = await axios.post(
                getBackendURL(),
                { type: "FETCH_SPECIFIC_STOCK_DATA", ticker: selectedTicker, startTime: formattedStart, endTime: formattedEnd },
                { withCredentials: true }
            );

            if (response.status === 200 && response.data.error) {
                window.alert(`Polygon doesn't have data for ${selectedTicker}. Choose another stock.`);
                window.history.back();
                return;
            }

            if (response.status === 200 && response.data) {
                console.log("Stock info:", response.data.stockInfo);
                const stockInfo = response.data.stockInfo;
                const newData = response.data.chartData.map(item => ({
                    time: item.timestamp,
                    open: parseFloat(item.open),
                    high: parseFloat(item.high),
                    low: parseFloat(item.low),
                    close: parseFloat(item.close),
                    volume: item.volume ? parseInt(item.volume, 10) : 0
                }));

                newData.sort((a, b) => a.time - b.time);
                setStockInfo(stockInfo);
                setChartData(newData);
                chartRef.current.timeScale().fitContent();
            }
        } catch (error) {
            console.error("Error fetching historical data:", error);
        }
    };

    useEffect(() => {
        console.log("Selected Ticker:", Ticker);
        if (!containerRef.current) return;

        // Initialize chart only once
        const chart = createChart(containerRef.current, {
            width: containerRef.current.clientWidth,
            height: containerRef.current.clientHeight,
        });

        chart.applyOptions({
            layout: { attributionLogo: false },
            crosshair: {
                mode: CrosshairMode.Normal,
                vertLine: { width: 2, color: "#C3BCDB44", style: LineStyle.Solid, labelBackgroundColor: "#FFFFFF" },
            },
            timeScale: { timeVisible: true, secondsVisible: false, rightOffset: 5 },
        });

        const seriesInstance = chart.addSeries(CandlestickSeries, {
            upColor: "#26a69a",
            downColor: "#ef5350",
            borderVisible: false,
            wickUpColor: "#26a69a",
            wickDownColor: "#ef5350",
        });

        chartRef.current = chart;
        setCandlestickSeries(seriesInstance);

        return () => chart.remove(); // Cleanup chart on unmount
    }, []);

    useEffect(() => {
        if (selectedTicker) {
            fetchHistoricalData();
        }
    }, [selectedTicker]);

    useEffect(() => {
        if (!candlestickSeries || chartData.length === 0) return;

        // Set data on chart when it is ready
        const aggregatedData = aggregateData(chartData, timeframe);
        candlestickSeries.setData(aggregatedData);
    }, [chartData, candlestickSeries, timeframe]);

    const aggregateData = (data, timeframe) => {
        const interval = aggregationIntervals[timeframe];
        const aggregated = [];
        let currentCandle = null;

        data.forEach(item => {
            const timestamp = Math.floor(item.time / interval) * interval;
            if (!currentCandle || currentCandle.time !== timestamp) {
                if (currentCandle) aggregated.push(currentCandle);
                currentCandle = { time: timestamp, open: item.open, high: item.high, low: item.low, close: item.close, volume: item.volume };
            } else {
                currentCandle.high = Math.max(currentCandle.high, item.high);
                currentCandle.low = Math.min(currentCandle.low, item.low);
                currentCandle.close = item.close;
                currentCandle.volume += item.volume;
            }
        });

        if (currentCandle) aggregated.push(currentCandle);
        return aggregated;
    };

    return (
        <>
            {/* Navbar Added Here */}
            <Navbar handleLogout={() => window.location.href = "/"} />

            <div style={{ display: "flex", flexDirection: "row", alignItems: "center", justifyContent: "center" }}>
                <div className="ChartContainer" style={{ width: "95vw", height: "90vh", backgroundColor: "teal", position: "relative", padding: "2rem", display: "flex", flexDirection: "row" }}>
                    <div className="TradingChart" ref={containerRef} style={{ width: "80%", height: "90%", position: "relative" }} />
                    <div style={{ position: "absolute", top: "0px", left: "20px" }}>
                        <select value={timeframe} onChange={e => setTimeframe(e.target.value)} style={{ padding: "5px", fontSize: "14px", borderRadius: "5px", border: "1px solid #ccc", backgroundColor: "#fff", cursor: "pointer" }}>
                            <option value="1m">1 Minute</option>
                            <option value="5m">5 Minutes</option>
                            <option value="30m">30 Minutes</option>
                            <option value="1h">1 Hour</option>
                            <option value="1d">Daily</option>
                        </select>
                    </div>
                </div>

                {stockInfo && (<Transaction stockData={stockInfo} chartData={chartData} />)}
            </div>
        </>
    );
}

export default ChartPage;
