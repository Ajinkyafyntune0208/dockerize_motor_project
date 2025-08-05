import React, { useEffect, useState } from "react";
import { Modal } from "react-bootstrap";
import { RiWifiOffLine, RiSignalWifiOffLine } from "react-icons/ri";
import styled, { keyframes } from "styled-components";

function NetworkPopup() {
  const [showPopup, setShowPopup] = useState(false);
  const [isWeakConnection, setIsWeakConnection] = useState(false);
  const [isOffline, setIsOffline] = useState(false);

  useEffect(() => {
    const handleOnline = () => {
      setShowPopup(false);
      setIsWeakConnection(false);
      setIsOffline(false);
    };

    const handleOffline = () => {
      setShowPopup(true);
      setIsWeakConnection(false);
      setIsOffline(true);
    };

    const handleConnectionChange = (event) => {
      if (
        event.type === "change" &&
        event.target.downlink &&
        event.target.downlink < 0.5 &&
        !isOffline
      ) {
        setIsWeakConnection(true);
      } else {
        setIsWeakConnection(false);
      }
    };

    window.addEventListener("online", handleOnline);
    window.addEventListener("offline", handleOffline);
    window.addEventListener("connectionchange", handleConnectionChange);

    return () => {
      window.removeEventListener("online", handleOnline);
      window.removeEventListener("offline", handleOffline);
      window.removeEventListener("connectionchange", handleConnectionChange);
    };
  }, [isOffline]);

  return (
    <>
      <Modal
        show={showPopup}
        onHide={() => setShowPopup(false)}
        backdrop="static"
        centered
        dialogClassName="network-popup-dialog"
      >
        <PopupContainer>
          <IconContainer>
            {isWeakConnection && !isOffline ? (
              <SignalWifiOffIcon />
            ) : (
              <WifiOffIcon />
            )}
          </IconContainer>
          <PopupText>
            <Title>
              {isWeakConnection && !isOffline
                ? "Poor Network Connection"
                : "Network Unavailable"}
            </Title>
            <Subtitle>
              {isWeakConnection && !isOffline
                ? "Please check your connection"
                : "Trying to reconnect"}
            </Subtitle>
          </PopupText>
        </PopupContainer>
      </Modal>
    </>
  );
}

export default NetworkPopup;

const PopupContainer = styled.div`
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  height: 100%;
  border-radius: 10px;
  overflow: hidden;
  z-index: 30 !important;
`;

const IconContainer = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  margin-bottom: 20px;
`;

const pulseAnimation = keyframes`
  0% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
  100% {
    opacity: 1;
  }
`;

const WifiOffIcon = styled(RiWifiOffLine)`
  font-size: 6em;
  color: #f44336;
  animation: ${pulseAnimation} 1s ease-in-out infinite;
`;

const SignalWifiOffIcon = styled(RiSignalWifiOffLine)`
  font-size: 6em;
  color: #ffc107;
  animation: ${pulseAnimation} 1s ease-in-out infinite;
`;

const PopupText = styled.div`
  text-align: center;
`;

const Title = styled.h1`
  font-size: 1.5em;
  font-weight: bold;
  margin-bottom: 0px;
`;

const Subtitle = styled.p`
  font-size: 1.2em;
  color: #666;
`;
