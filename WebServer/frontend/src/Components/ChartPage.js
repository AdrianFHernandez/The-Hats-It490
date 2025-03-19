import React, { useEffect, useRef, useState } from "react";
import { CandlestickSeries, createChart, CrosshairMode, LineStyle } from "lightweight-charts";
import { useParams } from "react-router-dom";
import axios from "axios";
import getBackendURL from "../Utils/backendURL";
import Transaction from "./Transaction";
import Navbar from "../Components/Navbar";

function ChartPage() {
    const { Ticker } = useParams();
    const containerRef = useRef(null);
    const chartRef = useRef(null);
    const candlestickSeriesRef = useRef(null);
    const [rawData, setRawData] = useState([]); // Store raw fetched data
    const [chartData, setChartData] = useState([]);
    const [timeframe, setTimeframe] = useState("1m");
    const [selectedTicker, setSelectedTicker] = useState(Ticker);
    const [stockInfo, setStockInfo] = useState(null);
    const [isFetching, setIsFetching] = useState(false);
    const [currentStartTime, setCurrentStartTime] = useState(null);
    const fetchedRanges = useRef(new Set()); // Store fetched date ranges

    const aggregationIntervals = {
        "1m": 60, "5m": 300, "30m": 1800, "1h": 3600, "4h": 14400
    };

    // Helper function to format date as "YYYY-MM-DD"
    const formatDate = (date) => date.toISOString().split("T")[0];

    const fetchHistoricalData = async (startTime = null) => {
        if (!selectedTicker || isFetching) return;

        let finalEndTime = new Date();
        let finalStartTime = new Date(finalEndTime.getTime() - 5 * 24 * 60 * 60 * 1000); 

        if (startTime) {
            finalEndTime = new Date(startTime);
            finalStartTime = new Date(finalEndTime.getTime() - 5 * 24 * 60 * 60 * 1000);
        }

        const formattedStart = formatDate(finalStartTime);
        const formattedEnd = formatDate(finalEndTime);

        // **Prevent redundant fetching**
        const rangeKey = `${formattedStart}_${formattedEnd}`;
        if (fetchedRanges.current.has(rangeKey)) {
            console.log(`Skipping fetch, already fetched: ${formattedStart} to ${formattedEnd}`);
            return;
        }

        console.log(`Fetching data for ${selectedTicker} from ${formattedStart} to ${formattedEnd}`);
        setIsFetching(true);

        try {
            const response = await axios.post(
                getBackendURL(),
                {
                    type: "FETCH_SPECIFIC_STOCK_DATA",
                    ticker: selectedTicker,
                    startTime: formattedStart,
                    endTime: formattedEnd
                },
                { withCredentials: true }
            );

            if (response.status === 200 && response.data) {
                console.log("Stock info:", response.data.stockInfo);
                const stockInfo = response.data.stockInfo;
                let newData = response.data.chartData.map(item => ({
                    time: parseInt(item.timestamp, 10),
                    open: parseFloat(item.open),
                    high: parseFloat(item.high),
                    low: parseFloat(item.low),
                    close: parseFloat(item.close),
                    volume: item.volume ? parseInt(item.volume, 10) : 0,
                }));

                // Remove duplicates and sort in ascending order
                const uniqueData = Array.from(new Map(newData.map(item => [item.time, item])).values());
                uniqueData.sort((a, b) => a.time - b.time);

                setStockInfo(stockInfo);
                setRawData((prevData) => [...uniqueData, ...prevData]);
                fetchedRanges.current.add(rangeKey); // **Mark range as fetched**
                setCurrentStartTime(formatDate(new Date(finalStartTime.getTime() - 24 * 60 * 60 * 1000))); 
            }
        } catch (error) {
            console.error("Error fetching historical data:", error);
        }

        setIsFetching(false);
    };

    // Aggregates data based on selected timeframe
    const aggregateData = (data, timeframe) => {
        const interval = aggregationIntervals[timeframe];
        const aggregated = [];
        let currentCandle = null;

        data.forEach(item => {
            const timestamp = Math.floor(item.time / interval) * interval;

            if (!currentCandle || currentCandle.time !== timestamp) {
                if (currentCandle) aggregated.push(currentCandle);
                currentCandle = { 
                    time: timestamp, 
                    open: item.open, 
                    high: item.high, 
                    low: item.low, 
                    close: item.close, 
                    volume: item.volume 
                };
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

    // Apply timeframe aggregation when timeframe changes
    useEffect(() => {
        if (rawData.length === 0) return;
        const aggregatedData = aggregateData(rawData, timeframe);
        setChartData(aggregatedData);
    }, [timeframe, rawData]);

    useEffect(() => {
        console.log("Selected Ticker:", Ticker);
        if (!containerRef.current) return;

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
        candlestickSeriesRef.current = seriesInstance;

        const handleScroll = () => {
            if (!chartRef.current || !candlestickSeriesRef.current || rawData.length === 0 || isFetching) return;
            if (!chartRef.current.timeScale()) return;

            const visibleRange = chartRef.current.timeScale().getVisibleRange();
            if (!visibleRange || !visibleRange.from) return;

            const firstDataPoint = rawData[0]?.time;
            const leftEdgeTimestamp = visibleRange.from;
            const THRESHOLD_SECONDS = aggregationIntervals[timeframe] * 2;

            if (leftEdgeTimestamp <= firstDataPoint + THRESHOLD_SECONDS) {
                console.log("User scrolled back, fetching older data...");
                fetchHistoricalData(currentStartTime);
            }
        };

        chart.timeScale().subscribeVisibleLogicalRangeChange(handleScroll);

        return () => {
            if (chartRef.current) {
                chart.timeScale().unsubscribeVisibleLogicalRangeChange(handleScroll);
                chartRef.current.remove();
                chartRef.current = null;
                candlestickSeriesRef.current = null;
            }
        };
    }, [chartData]);

    useEffect(() => {
        if (selectedTicker) {
            fetchHistoricalData();
        }
    }, [selectedTicker]);

    useEffect(() => {
        if (!candlestickSeriesRef.current || chartData.length === 0) return;
        candlestickSeriesRef.current.setData(chartData);
    }, [chartData]);

    return (
        <>
            <Navbar handleLogout={() => (window.location.href = "/")} />

            <div style={{ display: "flex", flexDirection: "row", alignItems: "center", justifyContent: "center" }}>
                <div className="ChartContainer" style={{ width: "95vw", height: "90vh", backgroundColor: "teal", position: "relative", padding: "2rem", display: "flex", flexDirection: "row" }}>
                    <div className="TradingChart" ref={containerRef} style={{ width: "80%", height: "90%", position: "relative" }} />
                    <div style={{ position: "absolute", top: "0px", left: "20px" }}>
                        <select value={timeframe} onChange={e => setTimeframe(e.target.value)} style={{ padding: "5px", fontSize: "14px", borderRadius: "5px", border: "1px solid #ccc", backgroundColor: "#fff", cursor: "pointer" }}>
                            <option value="1m">1 Minute</option>
                            <option value="5m">5 Minutes</option>
                            <option value="30m">30 Minutes</option>
                            <option value="1h">1 Hour</option>
                            <option value="4h">4 Hour</option>
                        </select>
                    </div>
                </div>

                {stockInfo && <Transaction stockData={stockInfo} chartData={chartData} />}
            </div>
        </>
    );
}

export default ChartPage;
