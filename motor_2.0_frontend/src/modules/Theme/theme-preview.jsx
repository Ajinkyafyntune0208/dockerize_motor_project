import { Card, LogoFn } from "components";
import { Logo } from "components/header/HeaderStyle";
import React from "react";
import { Badge, Col, Row } from "react-bootstrap";
import { FiEdit } from "react-icons/fi";
import { ExpandLess } from "@mui/icons-material";
//prettier-ignore
import { Container, Header, TraceId, FilterContainer, 
         Filter, ClearAll, Tabs, Tab, ToggleSwitch, 
         ChooseIdv, MainSection, Addons, Cards, Addon, 
         FilterMenuBoxCheckConatiner, Item, LogoImg, 
         QuoteCard, BuyNowBtn, HowerTabs, AddonItem, 
         FoldedRibbon, Buttons 
        } from './theme.style';

const ThemePreview = ({ primary, secondary, ternary, quaternary, fonts }) => {
  return (
    <Card title={"Preview"}>
      <Container style={{ fontFamily: fonts }} fonts={fonts}>
        <Header>
          <Logo src={LogoFn()} alt="logo" />
          <TraceId style={{ border: `1px solid ${primary}` }}>
            Trace Id: XXXXXXXXXXXXXXXX
          </TraceId>
        </Header>
        <hr />
        <FilterContainer>
          <Row>
            <Col>
              Brand-Model-Variant: <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              PREVIOUS POLICY TYPE: <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              PREVIOUS POLICY EXPIRY: <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              PREVIOUS NCB(Assumed) <FiEdit style={{ color: secondary }} />:
            </Col>
          </Row>
          <Row>
            <Col>
              Type | Fuel | RTO: <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              OWNERSHIP: INDIVIDUAL <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              INVOICE ON: 19-01-2024 <FiEdit style={{ color: secondary }} />
            </Col>
            <Col>
              NEW NCB: 0% <FiEdit style={{ color: secondary }} />
            </Col>
          </Row>
        </FilterContainer>
        <Filter>
          <ClearAll primary={primary}>Clear All</ClearAll>
          <span>X Quotes Found</span>
          <Tabs primary={primary}>
            <Tab primary={primary}>Comprehensive</Tab>
            <Tab>Third Party</Tab>
          </Tabs>
          <ChooseIdv primary={primary}>
            Choose your IDV <FiEdit style={{ color: secondary }} />
          </ChooseIdv>
          <ToggleSwitch
            primary={primary}
            type="switch"
            id="custom-switch"
            label={<span className="label-text">GST</span>}
            className="toggleBtn"
            defaultChecked={true}
          />
        </Filter>
        <MainSection>
          <Addons>
            <Addon>
              <Item style={{ borderBottom: "1px solid #d9d9d9" }}>
                Addons & Covers
              </Item>
            </Addon>
            <ToggleSwitch
              primary={primary}
              type="switch"
              id="custom-switch"
              label={<span className="label-text">Show Best Match</span>}
              className="toggleBtn"
              defaultChecked={true}
            />
            <Addon>
              <Item style={{ borderTop: "1px solid #d9d9d9" }}>CPA</Item>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    defaultChecked={true}
                    checked={true}
                  />
                  <label className="form-check-label">
                    {"Compulsory Personal Accident"}{" "}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"Compulsory Personal Accident (3 years)"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
            </Addon>
            <Addon>
              <Item style={{ borderTop: "1px solid #d9d9d9" }}>Addons</Item>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    defaultChecked={true}
                    checked={true}
                  />
                  <label className="form-check-label">
                    {"Zero Depreciation"}{" "}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"Road Side Assistance"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">{"Consumable"}</label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"Key Replacement"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"Engine Protector"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">{"NCB Protection"}</label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
            </Addon>
            <Addon>
              <Item style={{ borderTop: "1px solid #d9d9d9" }}>
                Accessories{" "}
              </Item>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input
                    type="checkbox"
                    className="form-check-input"
                    defaultChecked={true}
                    checked={true}
                  />
                  <label className="form-check-label">
                    {"Electrical Accessories"}{" "}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"Non-Electrical Accessories"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
              <FilterMenuBoxCheckConatiner primary={primary}>
                <div className="filterMenuBoxCheck">
                  <input type="checkbox" className="form-check-input" />
                  <label className="form-check-label">
                    {"External Bi-Fuel Kit CNG/LPG"}
                  </label>
                  <span style={{ marginLeft: "3px" }}></span>
                </div>
              </FilterMenuBoxCheckConatiner>
            </Addon>
          </Addons>
          <Cards>
            <QuoteCard>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                }}
              >
                <LogoImg>Logo</LogoImg>
                <FilterMenuBoxCheckConatiner primary={primary}>
                  <div className="filterMenuBoxCheck">
                    <input
                      type="checkbox"
                      defaultChecked
                      className="form-check-input"
                    />
                    <label className="form-check-label">{"Compare"}</label>
                    <span style={{ marginLeft: "3px" }}></span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  padding: "20px 0",
                  borderBottom: "1px solid #e3e4e8",
                  fontSize: "13px",
                }}
              >
                <div>
                  IDV Value <br /> ₹ XXX,XX
                </div>
                <BuyNowBtn primary={primary}>
                  BUY NOW <br />₹ XX,XXX
                </BuyNowBtn>
              </div>
              <HowerTabs primary={primary}>
                <Badge
                  variant="secondary"
                  style={{
                    zIndex: 997,
                  }}
                >
                  Features <ExpandLess className="arrowIcon" />
                </Badge>
              </HowerTabs>
              <AddonItem>
                <span>Base Premium</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <AddonItem>
                <span>Compulsory Personal Accident</span>
                <span>₹ XX,XXX</span>
              </AddonItem>

              <AddonItem>
                <span>Zero Depreciation</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <AddonItem>
                <span>Road Side Assistance</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <Buttons primary={primary}>
                <span>Cashless Garage</span>
                <span>Premium Breakup</span>
              </Buttons>
            </QuoteCard>
            <QuoteCard>
              <FoldedRibbon primary={primary}>Renewal Quote</FoldedRibbon>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                }}
              >
                <LogoImg>Logo</LogoImg>
                <FilterMenuBoxCheckConatiner primary={primary}>
                  <div className="filterMenuBoxCheck">
                    <input type="checkbox" className="form-check-input" />
                    <label className="form-check-label">{"Compare"}</label>
                    <span style={{ marginLeft: "3px" }}></span>
                  </div>
                </FilterMenuBoxCheckConatiner>
              </div>
              <div
                style={{
                  display: "flex",
                  justifyContent: "space-between",
                  alignItems: "center",
                  padding: "20px 0",
                  borderBottom: "1px solid #e3e4e8",
                  fontSize: "13px",
                }}
              >
                <div>
                  IDV Value <br /> ₹ XXX,XX
                </div>
                <BuyNowBtn primary={primary}>
                  BUY NOW <br />₹ XX,XXX
                </BuyNowBtn>
              </div>
              <HowerTabs primary={primary}>
                <Badge
                  variant="secondary"
                  style={{
                    zIndex: 997,
                  }}
                >
                  Features <ExpandLess className="arrowIcon" />
                </Badge>
              </HowerTabs>
              <AddonItem>
                <span>Base Premium</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <AddonItem>
                <span>Compulsory Personal Accident</span>
                <span>₹ XX,XXX</span>
              </AddonItem>

              <AddonItem>
                <span>Zero Depreciation</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <AddonItem>
                <span>Road Side Assistance</span>
                <span>₹ XX,XXX</span>
              </AddonItem>
              <Buttons primary={primary}>
                <span>Cashless Garage</span>
                <span>Premium Breakup</span>
              </Buttons>
            </QuoteCard>
          </Cards>
        </MainSection>
      </Container>
    </Card>
  );
};

export default ThemePreview;
