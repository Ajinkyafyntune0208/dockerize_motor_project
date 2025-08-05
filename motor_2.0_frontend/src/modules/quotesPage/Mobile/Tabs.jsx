import { Tab, TabWrapper } from "components";
import { TypeReturn } from "modules/type";
import React from "react";
import { Col } from "react-bootstrap";
import Skeleton from "react-loading-skeleton";
import swal from "sweetalert";

const Tabs = ({
  prefillLoading,
  updateQuoteLoader,
  temp_data,
  setTab,
  tab,
  type,
  isMobileIOS,
  lessthan993,
  lessthan376,
  lessthan413,
  lessthan420,
}) => {
  return (
    <Col lg={3} md={6} sm={6} xs="6">
      {prefillLoading || updateQuoteLoader ? (
        <TabWrapper
          width="290px"
          className="tabWrappers"
          style={{
            position: "relative",
            bottom: "57px",
          }}
        >
          <Skeleton
            width={236}
            height={30}
            style={{ display: lessthan993 ? "none" : "inline-block" }}
          ></Skeleton>
        </TabWrapper>
      ) : (
        <TabWrapper width="290px" className="tabWrappers">
          <Tab
            className="comprehensive_tab"
            isActive={Boolean(tab === "tab1")}
            onClick={() => setTab("tab1")}
          >
            {temp_data?.odOnly
              ? "Own Damage"
              : temp_data?.newCar && TypeReturn(type) !== "cv"
              ? lessthan376 || (isMobileIOS && lessthan420) || ""
                ? "Bundled"
                : "Bundled Policy"
              : "Comprehensive"}
          </Tab>
          <Tab
            className="tp_tab"
            isActive={Boolean(tab === "tab2")}
            disable={temp_data?.odOnly}
            onClick={() =>
              import.meta.env.VITE_BROKER !== "SRIYAH"
                ? !temp_data?.odOnly && setTab("tab2")
                : swal(
                    "Please Note",
                    "Third party quotes have been blocked.",
                    "info"
                  )
            }
            id={"tab2"}
            style={temp_data?.odOnly ? { cursor: "not-allowed" } : {}}
          >
            {lessthan376 || (isMobileIOS && lessthan413) || ""
              ? "TP"
              : "Third Party"}
          </Tab>
        </TabWrapper>
      )}
    </Col>
  );
};

export default Tabs;
