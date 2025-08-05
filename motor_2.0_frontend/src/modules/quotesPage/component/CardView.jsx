import React from "react";
import { Col, Dropdown } from "react-bootstrap";
import { AiOutlineProfile } from "react-icons/ai";
import { BsFillGrid3X3GapFill } from "react-icons/bs";
import Styled from "../quotesStyle";

const CardView = ({
  quotesLoaded,
  lessthan767,
  lessthan993,
  handleView,
  view,
}) => {
  return (
    <Col xl={2} lg={2} md={6} sm={6} xs={6}>
      {!quotesLoaded && !lessthan767 && !lessthan993 && (
        <Styled.ViewContainer>
          <Dropdown>
            <Dropdown.Toggle
             id={"card_view"}
              style={{
                border: "none",
                boxShadow: "none",
                background: "#fff",
                color:
                  import.meta.env.VITE_BROKER === "RB" ? "#1966FF" : "#000",
                fontSize: "14px",
              }}
            >
              Card View
            </Dropdown.Toggle>
            <Dropdown.Menu style={{ textAlign: "left" }}>
              <Dropdown.Item
                onClick={() => handleView("grid")}
                style={{
                  fontSize: "16px",
                  lineHeight: "35px",
                  padding: "0.25rempx !important",
                  width: "auto",
                }}
              >
                <Styled.IconTab isActive={Boolean(view === "grid")}>
                  <BsFillGrid3X3GapFill /> Grid
                </Styled.IconTab>
              </Dropdown.Item>
              <Dropdown.Item
                style={{
                  fontSize: "16px",
                  lineHeight: "35px",
                  padding: "0.25rempx !important",
                  width: "auto",
                }}
              >
                <Styled.IconTab
                  isActive={Boolean(view === "list")}
                  onClick={() => handleView("list")}
                >
                  <AiOutlineProfile /> List
                </Styled.IconTab>
              </Dropdown.Item>
            </Dropdown.Menu>
          </Dropdown>
        </Styled.ViewContainer>
      )}
    </Col>
  );
};

export default CardView;
