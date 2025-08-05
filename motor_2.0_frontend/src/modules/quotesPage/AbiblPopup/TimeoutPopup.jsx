import React, { useEffect } from "react";
import Popup from "components/Popup/Popup";
import styled from "styled-components";
import { useSelector } from "react-redux";
import { useMediaPredicate } from "react-media-hook";
import { ContactFn } from "components";
import { LinkTrigger } from "modules/Home/home.slice";
import { useDispatch } from "react-redux";
import { inactiveTracking } from "analytics/other-tracking/inactive-response";
import { TypeReturn } from "modules/type";
import { TitleState } from "modules/proposal/form-section/card-titles";

const TimeoutPopup = ({ show, onClose, enquiry_id, type, TempData }) => {
  const dispatch = useDispatch();
  const { theme_conf } = useSelector((state) => state.home);
  //Analytics | Inactive response
  const userResponse = (response) =>
    inactiveTracking(response, TypeReturn(type));
  //Timout beacon
  useEffect(() => {
    show &&
      dispatch(
        LinkTrigger(
          {
            user_product_journey_id: enquiry_id,
            dropout: "timeout",
          },
          true
        )
      );
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [show]);

  const lessthan767 = useMediaPredicate("(max-width: 767px)");
  const content = (
    <TopDiv>
      <div style={{ height: "200px", width: "100%", textAlign: "center" }}>
        <img
          src={
            import.meta.env.VITE_BROKER === "HEROCARE"
              ? TitleState(false, "image", "image", TypeReturn(type), TempData)
              : `${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ""
                }/assets/images/car-icon.jpg`
          }
          style={{
            height: "100%",
            border: "16px solid white",
            borderRadius: "50%",
          }}
          alt="car-icon"
        />
      </div>
      <h2
        className="text-center hello_text"
        style={{ color: "white", marginTop: "50px" }}
      >
        Hello, you seem to be inactive.
      </h2>
      <p
        className="mt-2 mb-4 help_text"
        style={{ color: "#fff", textAlign: "center" }}
      >
        Need help choosing the best plan? You can talk to us now.
      </p>
      <div
        style={{
          width: "100%",
          display: "flex",
          justifyContent: "space-around",
        }}
      >
        <button
          className="no-thanks-btn"
          style={{
            padding: "6px 30px",
            borderRadius: "8px",
            background: "#fff",
            border: "none",
            cursor: "pointer",
          }}
          onClick={() => [onClose(), userResponse("Not now")]}
        >
          No, Thanks
        </button>

        <a
          href={
            lessthan767 &&
            `tel:${theme_conf?.broker_config?.phone || ContactFn()}`
          }
          className="call-me-btn"
          style={{
            padding: "6px 30px",
            borderRadius: "8px",
            border: "none",
            color: "#fff",
            cursor: "pointer",
          }}
          onClick={() => {
            userResponse("Talk to us now");
            onClose();
            return (
              document?.getElementById("callus1") &&
              document?.getElementById("callus1").click()
            );
          }}
        >
          Talk to us Now.
        </a>
      </div>
    </TopDiv>
  );

  return (
    <div>
      <Popup
        width="500px"
        height="auto"
        hiddenClose
        show={show}
        content={content}
        backGround="transparent"
      />
    </div>
  );
};

export default TimeoutPopup;

const TopDiv = styled.div`
  .no-thanks-btn {
    color: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }
  .call-me-btn {
    background: ${({ theme }) => theme.floatButton?.floatColor || "#bdd400"};
  }
  .call-me-btn:hover {
    text-decoration: none;
  }
  @media (max-width: 576px) {
    .hello_text {
      font-size: 1.2rem;
    }
    .help_text {
      font-size: 0.9rem;
    }
  }
`;
