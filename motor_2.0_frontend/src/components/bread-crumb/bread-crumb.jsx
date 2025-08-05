import React from "react";
import { Breadcrumb } from "react-bootstrap";
import { useLocation } from "react-router-dom";
import styled from "styled-components";
import _ from "lodash";
import { fetchToken } from "utils";
import { useSelector } from "react-redux";

const ComponentsBreadCrumbs = () => {
  const location = useLocation();
  const { temp_data } = useSelector((state) => state.home);
  const loc = location.pathname ? location.pathname.split("/") : "";
  const type = !_.isEmpty(loc) ? (loc?.length >= 2 ? loc[1] : "") : "";

  const query = new URLSearchParams(location.search);
  const token = query.get("xutm") || localStorage?.SSO_user_motor;
  const typeId = query.get("typeid");
  const journey_type = query.get("journey_type");
  const enquiry_id = query.get("enquiry_id");
  const shared = query.get("shared");

  const _stToken = fetchToken();

  const inputPageLocation = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ``
  }/${type}/registration?enquiry_id=${temp_data?.enquiry_id || enquiry_id}${
    token ? `&xutm=${token}` : ``
  }${typeId ? `&typeid=${typeId}` : ``}${
    journey_type ? `&journey_type=${journey_type}` : ``
  }${_stToken ? `&stToken=${_stToken}` : ``}${
    shared ? `&shared=${shared}` : ``
  }`;

  const mnvPage = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ``
  }/${type}/vehicle-details?enquiry_id=${enquiry_id}${
    token ? `&xutm=${token}` : ``
  }${typeId ? `&typeid=${typeId}` : ``}${
    journey_type ? `&journey_type=${journey_type}` : ``
  }${_stToken ? `&stToken=${_stToken}` : ``}${
    shared ? `&shared=${shared}` : ``
  }`;

  const quotesPage = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ``
  }/${type}/quotes?enquiry_id=${enquiry_id}${token ? `&xutm=${token}` : ``}${
    typeId ? `&typeid=${typeId}` : ``
  }${journey_type ? `&journey_type=${journey_type}` : ``}${
    _stToken ? `&stToken=${_stToken}` : ``
  }${shared ? `&shared=${shared}` : ``}`;

  const compareQuote = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ``
  }/${type}/compare-quote?enquiry_id=${enquiry_id}${
    token ? `&xutm=${token}` : ``
  }${typeId ? `&typeid=${typeId}` : ``}${
    journey_type ? `&journey_type=${journey_type}` : ``
  }${_stToken ? `&stToken=${_stToken}` : ``}${
    shared ? `&shared=${shared}` : ``
  }`;

  const proposalPage = `${
    import.meta.env.VITE_BASENAME !== "NA"
      ? `/${import.meta.env.VITE_BASENAME}`
      : ``
  }/${type}/proposal-page?enquiry_id=${enquiry_id}${
    token ? `&xutm=${token}` : ``
  }${typeId ? `&typeid=${typeId}` : ``}${
    _stToken ? `&stToken=${_stToken}` : ``
  }${shared ? `&shared=${shared}` : ``}`;

  const handleBreadcrumbClick = (path) => {
    // Replace the current history state with a new state
    const newState = { breadcrumb: true };
    window.history.replaceState(newState, "", window.location.href);

    // Navigate to the new path
    window.location.replace(`${window.location.origin}${path}`);

    // Cleanup function to restore the original state when the component unmounts
    return () => {
      const originalState = { breadcrumb: false };
      window.history.replaceState(originalState, "", window.location.href);
    };
  };

  let breadcrumbs = null;

  if (
    location.pathname.includes("registration") ||
    location.pathname.includes("renewal")
  ) {
    breadcrumbs = (
      <Breadcrumb.Item
        active
        onClick={() => handleBreadcrumbClick(inputPageLocation)}
      >
        Registration Page
      </Breadcrumb.Item>
    );
  }

  if (
    location.pathname.includes("vehicle-details") ||
    location.pathname.includes("vehicle-type")
  ) {
    breadcrumbs = (
      <>
        <Breadcrumb.Item
          onClick={() => handleBreadcrumbClick(inputPageLocation)}
        >
          Registration Page
        </Breadcrumb.Item>
        <Breadcrumb.Item active onClick={() => handleBreadcrumbClick(mnvPage)}>
          Vehicle Details
        </Breadcrumb.Item>
      </>
    );
  }

  if (location.pathname.includes("quotes")) {
    breadcrumbs = (
      <>
        <Breadcrumb.Item
          onClick={() => handleBreadcrumbClick(inputPageLocation)}
        >
          Registration Page
        </Breadcrumb.Item>
        <Breadcrumb.Item onClick={() => handleBreadcrumbClick(mnvPage)}>
          Vehicle Details
        </Breadcrumb.Item>
        <Breadcrumb.Item
          active
          onClick={() => handleBreadcrumbClick(quotesPage)}
        >
          Quotes Page
        </Breadcrumb.Item>
      </>
    );
  }

  if (location.pathname.includes("compare-quote")) {
    breadcrumbs = (
      <>
        <Breadcrumb.Item
          onClick={() => handleBreadcrumbClick(inputPageLocation)}
        >
          Registration Page
        </Breadcrumb.Item>
        <Breadcrumb.Item onClick={() => handleBreadcrumbClick(mnvPage)}>
          Vehicle Details
        </Breadcrumb.Item>
        <Breadcrumb.Item onClick={() => handleBreadcrumbClick(quotesPage)}>
          Quotes Page
        </Breadcrumb.Item>
        <Breadcrumb.Item
          active
          onClick={() => handleBreadcrumbClick(compareQuote)}
        >
          Compare Page
        </Breadcrumb.Item>
      </>
    );
  }

  if (location.pathname.includes("proposal-page")) {
    breadcrumbs = (
      <>
        <Breadcrumb.Item
          onClick={() => handleBreadcrumbClick(inputPageLocation)}
        >
          Registration Page
        </Breadcrumb.Item>
        <Breadcrumb.Item onClick={() => handleBreadcrumbClick(mnvPage)}>
          Vehicle Details
        </Breadcrumb.Item>
        <Breadcrumb.Item onClick={() => handleBreadcrumbClick(quotesPage)}>
          Quotes Page
        </Breadcrumb.Item>
        <Breadcrumb.Item
          active
          onClick={() => handleBreadcrumbClick(proposalPage)}
        >
          Proposal Page
        </Breadcrumb.Item>
      </>
    );
  }

  return (
    <Container>
      <Breadcrumb>{breadcrumbs}</Breadcrumb>
    </Container>
  );
};

export default ComponentsBreadCrumbs;

const Container = styled.div`
  .breadcrumb {
    padding: 0 !important;
    margin-bottom: 0px !important;
    background-color: #fff !important;
    margin-left: 15px !important;
    font-size: 14px;
  }
`;
